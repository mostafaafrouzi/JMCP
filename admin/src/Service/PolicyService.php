<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Component\Jmcp\Administrator\Service\Tier\FeatureGate;
use Joomla\Registry\Registry;

class PolicyService
{
    private Registry $params;
    private AbilityHubService $hub;
    private FeatureGate $featureGate;

    /** @var array<string, string> */
    private const TOOL_CAPABILITY_MAP = [
        'list_directory' => 'allow_file_read', 'read_file' => 'allow_file_read',
        'get_media' => 'allow_file_read', 'list_media' => 'allow_file_read',
        'write_file' => 'allow_file_write', 'edit_file' => 'allow_file_write',
        'delete_file' => 'allow_file_write', 'create_template_override' => 'allow_file_write',
        'upload_media' => 'allow_file_write', 'update_media' => 'allow_file_write',
        'delete_media' => 'allow_file_write', 'create_media_folder' => 'allow_file_write',
        'list_db_tables' => 'allow_sql_exec', 'get_db_table_columns' => 'allow_sql_exec',
        'execute_sql' => 'allow_sql_exec',
        'execute_php' => 'allow_php_exec',
        'run_cli_command' => 'allow_cli_exec', 'run_cache_clean' => 'allow_cli_exec',
        'check_core_updates' => 'allow_cli_exec', 'akeeba_create_backup' => 'allow_cli_exec',
    ];

    public function __construct(Registry $params, ?FeatureGate $featureGate = null)
    {
        $this->params = $params;
        $this->hub = new AbilityHubService($params);
        $this->featureGate = $featureGate ?? new FeatureGate();
    }

    public function getFeatureGate(): FeatureGate
    {
        return $this->featureGate;
    }

    public function isMcpEnabled(): bool
    {
        if (!(bool) $this->params->get('mcp_enabled', 1)) {
            return false;
        }

        if ((bool) $this->params->get('domain_lock', 0)) {
            $lockedHost = (string) $this->params->get('domain_lock_host', '');
            $currentHost = Uri::getInstance()->getHost();
            if ($lockedHost !== '' && $lockedHost !== $currentHost) {
                return false;
            }
        }

        return true;
    }

    public function isToolAllowed(string $toolName): bool
    {
        if (!$this->isMcpEnabled()) {
            return false;
        }

        if (!$this->hub->isToolEnabled($toolName)) {
            return false;
        }

        if (!$this->featureGate->canUseTool($toolName)) {
            return false;
        }

        $capability = self::TOOL_CAPABILITY_MAP[$toolName] ?? null;
        if ($capability === null) {
            return true;
        }

        return (bool) $this->params->get($capability, 0);
    }

    public function getCapabilityForTool(string $toolName): ?string
    {
        if (!$this->hub->isToolEnabled($toolName)) {
            return 'disabled_tools';
        }
        if (!$this->featureGate->canUseTool($toolName)) {
            return 'pro_required';
        }
        return self::TOOL_CAPABILITY_MAP[$toolName] ?? null;
    }

    public function filterTools(array $tools): array
    {
        return array_values(array_filter(
            $tools,
            fn(array $tool): bool => $this->isToolAllowed((string) ($tool['name'] ?? ''))
        ));
    }

    public function isProductionWarningRequired(): bool
    {
        return (bool) $this->params->get('production_warning', 1)
            && (
                (bool) $this->params->get('allow_php_exec', 0)
                || (bool) $this->params->get('allow_sql_exec', 0)
                || (bool) $this->params->get('allow_file_write', 0)
            );
    }

    public function isDestructiveBlockedOnProduction(string $toolName): bool
    {
        if (!(bool) $this->params->get('production_block_destructive', 0)) {
            return false;
        }

        if (!$this->isProductionWarningRequired()) {
            return false;
        }

        return $this->hub->getRiskLevel($toolName) === 'destructive';
    }
}
