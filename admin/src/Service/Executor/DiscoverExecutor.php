<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Component\Jmcp\Administrator\Service\AbilityHubService;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;
use Joomla\Component\Jmcp\Administrator\Service\SkillRegistry;
use Joomla\Component\Jmcp\Administrator\Service\Tier\LicenseService;
use Joomla\Component\Jmcp\Administrator\Service\Tier\TierRegistry;
use Joomla\Component\Jmcp\Administrator\Service\ToolRegistry;
use Joomla\Registry\Registry;

class DiscoverExecutor
{
    public function discoverTools(array $params, ToolRegistry $registry, Registry $config): array
    {
        $detector = new IntegrationDetector();
        $hub = new AbilityHubService($config);
        $skills = new SkillRegistry();
        $version = new Version();
        $license = new LicenseService($config);
        $tiers = new TierRegistry();

        $tools = [];
        foreach ($registry->getAll() as $tool) {
            $name = (string) $tool['name'];
            $tools[] = [
                'name'        => $name,
                'description' => $tool['description'] ?? '',
                'enabled'     => $hub->isToolEnabled($name),
                'risk'        => $hub->getRiskLevel($name),
            ];
        }

        return [
            'server'        => (string) $config->get('server_name', 'joomla-mcp-server'),
            'joomla_version'=> $version->getShortVersion(),
            'site_url'      => Uri::root(),
            'mcp_endpoint'    => Uri::root() . 'index.php?option=com_jmcp&task=rpc.handle',
            'health_endpoint' => Uri::root() . 'index.php?option=com_jmcp&task=health.check',
            'integrations'  => $detector->getInstalledList(),
            'shops'         => $detector->getInstalledShops(),
            'capabilities'  => [
                'file_read'  => (bool) $config->get('allow_file_read', 1),
                'file_write' => (bool) $config->get('allow_file_write', 0),
                'sql'        => (bool) $config->get('allow_sql_exec', 0),
                'php'        => (bool) $config->get('allow_php_exec', 0),
                'cli'        => (bool) $config->get('allow_cli_exec', 0),
            ],
            'tools'         => $tools,
            'skills'        => array_column($skills->getPrompts(), 'name'),
            'instructions'  => $this->instructions(),
            'license'       => $license->getStatus(),
            'pro_tools'     => $tiers->getProTools(),
            'is_pro'        => $license->isProActive(),
        ];
    }

    private function instructions(): string
    {
        return implode("\n", [
            '1. Call get_site_info first.',
            '2. Use discover_tools to see enabled tools and integrations.',
            '3. Trash articles before permanent delete (force=false).',
            '4. For shops: detect_installed_shops then use matching virtuemart_*/hikashop_*/j2commerce_* tools.',
            '5. For SEO: analyze_page_seo then update_article_seo_meta.',
            '6. Use create_pending_change for destructive ops on production sites.',
            '7. Template overrides: create_template_override or write_file under templates/*/html/.',
        ]);
    }
}
