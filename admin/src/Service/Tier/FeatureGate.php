<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Tier;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Central gate: combines tier (Free/Pro) with per-tool policy.
 */
class FeatureGate
{
    private TierRegistry $tiers;
    private LicenseService $license;

    public function __construct(?TierRegistry $tiers = null, ?LicenseService $license = null)
    {
        $this->tiers   = $tiers ?? new TierRegistry();
        $this->license = $license ?? new LicenseService();
    }

    public function canUseTool(string $toolName): bool
    {
        if (!$this->tiers->isProTool($toolName)) {
            return true;
        }

        return $this->license->isProActive();
    }

    public function getBlockReason(string $toolName): ?string
    {
        if ($this->canUseTool($toolName)) {
            return null;
        }

        $feature = $this->tiers->getProFeatureForTool($toolName) ?? 'pro';

        return Text::sprintf('COM_JMCP_ERR_PRO_REQUIRED', $toolName, $feature);
    }

    public function getLicenseService(): LicenseService
    {
        return $this->license;
    }

    public function getTierRegistry(): TierRegistry
    {
        return $this->tiers;
    }
}
