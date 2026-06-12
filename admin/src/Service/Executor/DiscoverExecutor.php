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
            '4. For shops: detect_installed_shops then virtuemart_list_products / virtuemart_update_product / virtuemart_update_category.',
            '5. For SP Page Builder: get_sp_page reads content column; save_sp_page and bulk_replace_sp_content write it.',
            '6. For site-wide text changes: search_site_content then bulk_content_replace (preset sp_pages, articles, menus, etc.).',
            '7. For full rebrand: site_rebrand(brand, old_brand) with dry_run=true first, then run_cache_clean.',
            '8. VirtueMart: virtuemart_set_product_price, virtuemart_assign_product_categories, virtuemart_manage_product_media.',
            '9. SP: update_sp_page_meta, list_sp_page_modules, bulk_replace_sp_content; Helix: get/update_helix_menu_layout.',
            '10. Core ops: update_component_params, finder_rebuild_index, toggle_extension, create/update_user.',
            '11. For SEO: analyze_page_seo then update_schemaorg_for_item.',
            '12. Use create_pending_change for destructive ops on production sites.',
        ]);
    }
}
