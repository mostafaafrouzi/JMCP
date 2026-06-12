<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Tier;

defined('_JEXEC') or die;

/**
 * Defines which JMCP capabilities belong to Free vs Pro tiers.
 * Payment/licensing is handled separately by LicenseService.
 */
class TierRegistry
{
    public const TIER_FREE = 'free';
    public const TIER_PRO  = 'pro';

    /** @var string[] Tools reserved for JMCP Pro (blocked when Pro is not active). */
    private const PRO_TOOLS = [
        'virtuemart_list_products', 'virtuemart_get_product', 'virtuemart_save_product', 'virtuemart_list_orders',
        'hikashop_list_products', 'hikashop_get_product', 'hikashop_save_product', 'hikashop_list_orders',
        'j2commerce_list_products', 'j2commerce_get_product', 'j2commerce_save_product',
        'get_helix_layout', 'update_helix_params', 'list_helix_positions',
        'bulk_update_meta', 'duplicate_sp_page',
        'akeeba_create_backup',
        'trigger_webhook',
        'memory_store', 'memory_search', 'memory_list',
    ];

    /** @var string[] Feature flags (non-tool) gated behind Pro. */
    private const PRO_FEATURES = [
        'persistent_memory',
        'webhook_dispatch',
        'shop_integrations',
        'helix_integration',
        'advanced_seo_bulk',
        'akeeba_backup_trigger',
    ];

    public function isProTool(string $toolName): bool
    {
        return in_array($toolName, self::PRO_TOOLS, true);
    }

    public function isProFeature(string $feature): bool
    {
        return in_array($feature, self::PRO_FEATURES, true);
    }

    /** @return string[] */
    public function getProTools(): array
    {
        return self::PRO_TOOLS;
    }

    /** @return string[] */
    public function getProFeatures(): array
    {
        return self::PRO_FEATURES;
    }

    /**
     * Map a tool name to a Pro feature category for upsell messaging.
     */
    public function getProFeatureForTool(string $toolName): ?string
    {
        if (str_starts_with($toolName, 'virtuemart_') || str_starts_with($toolName, 'hikashop_') || str_starts_with($toolName, 'j2commerce_')) {
            return 'shop_integrations';
        }
        if (str_starts_with($toolName, 'get_helix_') || str_starts_with($toolName, 'update_helix_') || str_starts_with($toolName, 'list_helix_')) {
            return 'helix_integration';
        }
        if ($toolName === 'bulk_update_meta') {
            return 'advanced_seo_bulk';
        }
        if ($toolName === 'trigger_webhook') {
            return 'webhook_dispatch';
        }
        if (str_starts_with($toolName, 'memory_')) {
            return 'persistent_memory';
        }
        if ($toolName === 'akeeba_create_backup') {
            return 'akeeba_backup_trigger';
        }

        return null;
    }
}
