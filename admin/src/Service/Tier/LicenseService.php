<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Tier;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/**
 * Detects JMCP Pro activation. No payment processing — structure only.
 *
 * Pro is active when ANY of:
 *  1. Constant JMCP_PRO_VERSION is defined (companion plugin/package)
 *  2. com_jmcp params license_key validates (future)
 *  3. plg_system_jmcppro is enabled (future companion plugin)
 */
class LicenseService
{
    private Registry $params;

    public function __construct(?Registry $params = null)
    {
        $this->params = $params ?? ComponentHelper::getParams('com_jmcp');
    }

    public function isProActive(): bool
    {
        if (defined('JMCP_PRO_VERSION')) {
            return true;
        }

        if ($this->isCompanionPluginEnabled()) {
            return true;
        }

        $key = trim((string) $this->params->get('license_key', ''));
        if ($key !== '' && $this->validateLicenseKeyFormat($key)) {
            // Format-valid key accepted in dev; real validation hooks here later.
            return (bool) $this->params->get('license_key_accepted', 0);
        }

        return false;
    }

    public function getTier(): string
    {
        return $this->isProActive() ? TierRegistry::TIER_PRO : TierRegistry::TIER_FREE;
    }

    public function getProVersion(): ?string
    {
        if (defined('JMCP_PRO_VERSION')) {
            return (string) JMCP_PRO_VERSION;
        }
        return $this->isProActive() ? 'companion' : null;
    }

    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        return [
            'tier'        => $this->getTier(),
            'is_pro'      => $this->isProActive(),
            'pro_version' => $this->getProVersion(),
            'sources'     => [
                'constant'       => defined('JMCP_PRO_VERSION'),
                'companion_plugin' => $this->isCompanionPluginEnabled(),
                'license_key'    => trim((string) $this->params->get('license_key', '')) !== '',
            ],
        ];
    }

    private function isCompanionPluginEnabled(): bool
    {
        $db = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('enabled')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('jmcppro'));

        return (int) $db->setQuery($query)->loadResult() === 1;
    }

    private function validateLicenseKeyFormat(string $key): bool
    {
        return (bool) preg_match('/^JMCP-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key);
    }
}
