<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version as JoomlaVersion;
use Joomla\Registry\Registry;
use Psr\Log\LoggerInterface;

class RpcService
{
    private const SUPPORTED_PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private static ?string $cachedVersion = null;

    private CacheService $cache;
    private PolicyService $policy;
    private LoggerInterface $logger;
    private ToolRegistry $toolRegistry;
    private SchemaValidator $validator;
    private ResourceProvider $resources;
    private SkillRegistry $skills;
    private AuditService $audit;
    private string $serverName;
    private Registry $params;

    public function __construct(
        CacheService $cache,
        PolicyService $policy,
        LoggerInterface $logger,
        ToolRegistry $toolRegistry,
        SchemaValidator $validator,
        string $serverName = 'joomla-mcp-server',
        ?Registry $params = null
    ) {
        $this->cache        = $cache;
        $this->policy       = $policy;
        $this->logger       = $logger;
        $this->toolRegistry = $toolRegistry;
        $this->validator    = $validator;
        $this->resources    = new ResourceProvider();
        $this->skills       = new SkillRegistry();
        $this->audit        = new AuditService();
        $this->serverName   = $serverName;
        $this->params       = $params ?? ComponentHelper::getParams('com_jmcp');

        (new ToolExecutorRegistry())->register($this->toolRegistry, $this->params);
    }

    public function handle(array $request): ?array
    {
        $id             = $request['id'] ?? null;
        $isNotification = !array_key_exists('id', $request);
        $method         = (string) ($request['method'] ?? '');
        $params         = is_array($request['params'] ?? null) ? $request['params'] : [];

        $this->logger->info('Handling RPC request', [
            'method' => $method,
            'has_id' => !$isNotification,
            'server' => $this->serverName,
        ]);

        if (!$this->policy->isMcpEnabled()) {
            $response = JsonRpc::errorResponse($id, JsonRpc::FORBIDDEN, 'MCP server is disabled in component settings.');
            return $isNotification ? null : $response;
        }

        if (in_array($method, [
            'notifications/initialized',
            'notifications/cancelled',
            'notifications/progress',
            'notifications/roots/list_changed',
        ], true)) {
            return $isNotification ? null : JsonRpc::successResponse($id, null);
        }

        if ($method === 'initialize' || $method === 'capabilities') {
            $response = $this->handleCapabilities($id, $params);
            return $isNotification ? null : $response;
        }

        if ($method === 'ping') {
            return $isNotification ? null : JsonRpc::successResponse($id, new \stdClass());
        }

        if ($method === 'tools/list') {
            $response = $this->handleListTools($id);
            return $isNotification ? null : $response;
        }

        if ($method === 'tools/call') {
            $response = $this->handleCallTool($id, $params);
            return $isNotification ? null : $response;
        }

        if ($method === 'resources/list') {
            $response = JsonRpc::successResponse($id, ['resources' => $this->resources->listResources()]);
            return $isNotification ? null : $response;
        }

        if ($method === 'resources/read') {
            $uri = (string) ($params['uri'] ?? '');
            try {
                $content = $this->resources->readResource($uri);
                $response = JsonRpc::successResponse($id, [
                    'contents' => [['uri' => $uri, 'mimeType' => 'application/json', 'text' => json_encode($content, JSON_UNESCAPED_UNICODE)]],
                ]);
            } catch (\Throwable $e) {
                $response = JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, $e->getMessage());
            }
            return $isNotification ? null : $response;
        }

        if ($method === 'prompts/list') {
            $response = JsonRpc::successResponse($id, ['prompts' => $this->skills->getPrompts()]);
            return $isNotification ? null : $response;
        }

        if ($method === 'prompts/get') {
            $name = (string) ($params['name'] ?? '');
            $args = (array) ($params['arguments'] ?? []);
            $content = $this->skills->getPromptContent($name, $args);
            if ($content === null) {
                $response = JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, 'Prompt not found.');
            } else {
                $response = JsonRpc::successResponse($id, [
                    'description' => $name,
                    'messages'    => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $content]]],
                ]);
            }
            return $isNotification ? null : $response;
        }

        if (in_array($method, ['resources/templates/list', 'logging/setLevel'], true)) {
            $empty = $method === 'resources/templates/list' ? ['resourceTemplates' => []] : new \stdClass();
            return $isNotification ? null : JsonRpc::successResponse($id, $empty);
        }

        if ($method === 'site_health') {
            $version = new JoomlaVersion();
            $response = JsonRpc::successResponse($id, [
                'status'         => 'ok',
                'joomla_version' => $version->getShortVersion(),
                'timestamp'      => Factory::getDate('now', 'UTC')->toSql(true),
            ]);
            return $isNotification ? null : $response;
        }

        $response = JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, 'Requested method not implemented');
        return $isNotification ? null : $response;
    }

    private function handleCapabilities(mixed $id, array $params = []): array
    {
        $clientVersion = $params['protocolVersion'] ?? null;
        $negotiatedVersion = is_string($clientVersion) && in_array($clientVersion, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $clientVersion
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];

        return JsonRpc::successResponse($id, [
            'protocolVersion' => $negotiatedVersion,
            'capabilities'    => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name'    => $this->serverName,
                'version' => $this->getComponentVersion(),
            ],
            'instructions' => $this->getServerInstructions(),
        ]);
    }

    private function handleListTools(mixed $id): array
    {
        $tools = $this->policy->filterTools($this->toolRegistry->getAll());

        return JsonRpc::successResponse($id, [
            'tools' => $tools,
        ]);
    }

    private function handleCallTool(mixed $id, array $params): array
    {
        $toolName = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($toolName === '') {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, 'Tool name is required.');
        }

        if (!$this->policy->isToolAllowed($toolName)) {
            $capability = $this->policy->getCapabilityForTool($toolName);
            if ($capability === 'pro_required') {
                $message = $this->policy->getFeatureGate()->getBlockReason($toolName)
                    ?: Text::_('COM_JMCP_ERR_PRO_GENERIC');
            } elseif ($capability) {
                $message = sprintf(Text::_('COM_JMCP_ERR_CAPABILITY_DISABLED'), $capability);
            } else {
                $message = 'This tool is not available.';
            }
            return JsonRpc::errorResponse($id, JsonRpc::FORBIDDEN, $message);
        }

        if ($this->policy->isDestructiveBlockedOnProduction($toolName)) {
            return JsonRpc::errorResponse(
                $id,
                JsonRpc::FORBIDDEN,
                Text::_('COM_JMCP_ERR_PRODUCTION_DESTRUCTIVE_BLOCKED')
            );
        }

        $tool = $this->toolRegistry->get($toolName);
        if ($tool === null) {
            return JsonRpc::errorResponse($id, JsonRpc::METHOD_NOT_FOUND, sprintf("Tool '%s' not found.", $toolName));
        }

        $validationError = $this->validator->validate($arguments, $tool['inputSchema'] ?? []);
        if ($validationError !== null) {
            return JsonRpc::errorResponse($id, JsonRpc::INVALID_PARAMS, $validationError);
        }

        if (!empty($arguments['dry_run'])) {
            $preview = ['dry_run' => true, 'tool' => $toolName, 'arguments' => $arguments, 'message' => 'No changes applied.'];
            $this->audit->log($toolName, 'dry_run', $arguments, true);
            return JsonRpc::successResponse($id, [
                'content' => [['type' => 'text', 'text' => json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]],
                'structuredContent' => $preview,
                'isError' => false,
            ]);
        }

        try {
            $result = $this->toolRegistry->execute($toolName, $arguments);
            $this->audit->log($toolName, 'execute', ['success' => true]);
            $this->dispatchWebhookIfNeeded($toolName, $arguments, $result);

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'structuredContent' => $result,
                'isError'           => false,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tool execution failed', [
                'tool'  => $toolName,
                'error' => $e->getMessage(),
            ]);

            return JsonRpc::successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'success' => false,
                            'error'   => $e->getMessage(),
                            'tool'    => $toolName,
                            'hint'    => $this->getRepairHint($toolName, $e->getMessage()),
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    ],
                ],
                'isError' => true,
            ]);
        }
    }

    private function getServerInstructions(): string
    {
        return implode("\n", [
            'You are connected to a Joomla site via JMCP (Joomla Model Context Protocol Server).',
            'Use get_site_info first to understand the environment, installed extensions, and endpoints.',
            'For articles: prefer delete_article with force=false (trash) before permanent deletion.',
            'Permanent delete requires force=true, article in trash, and expected_title matching.',
            'For template overrides: use read_file/write_file under templates/YOUR_TEMPLATE/html/.',
            'For SP Page Builder, Helix Ultimate, VirtueMart, HikaShop, J2Commerce: use list_extensions, execute_sql, or execute_php when dedicated tools are unavailable.',
            'Respect component capability toggles — filesystem, SQL, PHP, and CLI tools may be disabled by the site administrator.',
            'Always confirm destructive operations with the user when possible.',
        ]);
    }

    private function getRepairHint(string $toolName, string $error): string
    {
        if (str_contains($error, 'not found')) {
            return 'Verify the ID exists using the corresponding list_* tool.';
        }

        if (str_contains($error, 'capability') || str_contains($error, 'disabled')) {
            return 'Ask the administrator to enable the required capability in Components → JMCP → Options.';
        }

        if ($toolName === 'execute_sql') {
            return 'Use list_db_tables and get_db_table_columns to inspect schema before writing queries.';
        }

        return 'Check tool parameters against the inputSchema and retry with corrected values.';
    }

    private function getComponentVersion(): string
    {
        if (self::$cachedVersion !== null) {
            return self::$cachedVersion;
        }

        $manifest = null;
        foreach ([
            JPATH_ADMINISTRATOR . '/components/com_jmcp/jmcp.xml',
            JPATH_ADMINISTRATOR . '/components/com_jmcp/com_jmcp.xml',
            dirname(__DIR__, 4) . '/jmcp.xml',
        ] as $candidate) {
            if (is_file($candidate)) {
                $manifest = $candidate;
                break;
            }
        }
        if (empty($manifest)) {
            self::$cachedVersion = '1.0.0';
            return self::$cachedVersion;
        }

        if (is_file($manifest)) {
            $xml = simplexml_load_file($manifest);
            if ($xml && isset($xml->version)) {
                self::$cachedVersion = (string) $xml->version;
                return self::$cachedVersion;
            }
        }

        self::$cachedVersion = '1.0.0';
        return self::$cachedVersion;
    }

    private function dispatchWebhookIfNeeded(string $toolName, array $arguments, array $result): void
    {
        $hub = new AbilityHubService($this->params);
        $risk = $hub->getRiskLevel($toolName);

        if (!in_array($risk, ['destructive', 'execute'], true)) {
            return;
        }

        try {
            (new WebhookService($this->params))->dispatch('jmcp.tool.executed', [
                'tool'      => $toolName,
                'risk'      => $risk,
                'arguments' => $arguments,
                'result'    => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Webhook dispatch failed', ['tool' => $toolName, 'error' => $e->getMessage()]);
        }
    }
}
