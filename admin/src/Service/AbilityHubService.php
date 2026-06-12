<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/**
 * Per-tool enable/disable policy (Abilities Hub).
 */
class AbilityHubService
{
    /** @var array<string, string> risk: read|write|destructive|execute */
    private const TOOL_RISK = [
        'get_site_info' => 'read', 'discover_tools' => 'read', 'list_extensions' => 'read',
        'list_articles' => 'read', 'get_article' => 'read', 'create_article' => 'write',
        'update_article' => 'write', 'delete_article' => 'destructive',
        'execute_sql' => 'execute', 'execute_php' => 'execute', 'run_cli_command' => 'execute',
        'write_file' => 'write', 'delete_file' => 'destructive',
        'upload_media' => 'write', 'delete_media' => 'destructive',
        'akeeba_create_backup' => 'write', 'approve_pending_change' => 'write',
    ];

    private Registry $params;

    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    public function isToolEnabled(string $toolName): bool
    {
        $disabled = $this->getDisabledTools();
        return !in_array($toolName, $disabled, true);
    }

    /** @return string[] */
    public function getDisabledTools(): array
    {
        $raw = (string) $this->params->get('disabled_tools', '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param string[] $tools */
    public function saveDisabledTools(array $tools): void
    {
        $clean = array_values(array_unique(array_filter(array_map('strval', $tools))));
        (new ComponentParamsService())->set('disabled_tools', json_encode($clean));
    }

    /** @return array<string, array{enabled: bool, risk: string, tier: string, pro_only: bool}> */
    public function getEnrichedPolicies(array $tools, ?\Joomla\Component\Jmcp\Administrator\Service\Tier\FeatureGate $gate = null): array
    {
        $gate = $gate ?? new \Joomla\Component\Jmcp\Administrator\Service\Tier\FeatureGate();
        $tierRegistry = $gate->getTierRegistry();
        $license = $gate->getLicenseService();
        $disabled = $this->getDisabledTools();
        $policies = [];

        foreach ($tools as $tool) {
            $name = (string) ($tool['name'] ?? '');
            $proOnly = $tierRegistry->isProTool($name);
            $policies[$name] = [
                'name'     => $name,
                'enabled'  => !in_array($name, $disabled, true),
                'risk'     => $this->getRiskLevel($name),
                'tier'     => $proOnly ? 'pro' : 'free',
                'pro_only' => $proOnly,
                'available'=> $proOnly ? $license->isProActive() : true,
            ];
        }

        return array_values($policies);
    }

    public function getRiskLevel(string $toolName): string
    {
        if (isset(self::TOOL_RISK[$toolName])) {
            return self::TOOL_RISK[$toolName];
        }

        if (str_starts_with($toolName, 'delete_') || str_contains($toolName, '_delete')) {
            return 'destructive';
        }
        if (str_starts_with($toolName, 'create_') || str_starts_with($toolName, 'update_') || str_starts_with($toolName, 'save_')) {
            return 'write';
        }
        if (str_contains($toolName, 'execute_') || str_contains($toolName, 'run_')) {
            return 'execute';
        }
        if (str_starts_with($toolName, 'list_') || str_starts_with($toolName, 'get_') || str_starts_with($toolName, 'analyze_')) {
            return 'read';
        }

        return 'write';
    }

    /** @return array<int, array<string, mixed>> */
    public function getToolPolicies(array $tools): array
    {
        $disabled = $this->getDisabledTools();
        $policies = [];

        foreach ($tools as $tool) {
            $name = (string) ($tool['name'] ?? '');
            $policies[] = [
                'name'    => $name,
                'enabled' => !in_array($name, $disabled, true),
                'risk'    => $this->getRiskLevel($name),
            ];
        }

        return $policies;
    }
}
