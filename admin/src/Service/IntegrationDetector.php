<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Detects installed Joomla extensions relevant to JMCP integrations.
 */
class IntegrationDetector
{
    /** @var array<string, array{element: string, type: string, name: string}> */
    private const INTEGRATIONS = [
        'sppagebuilder'  => ['element' => 'com_sppagebuilder', 'type' => 'component', 'name' => 'SP Page Builder'],
        'helixultimate'  => ['element' => 'tpl_hlex', 'type' => 'template', 'name' => 'Helix Ultimate'],
        'virtuemart'     => ['element' => 'com_virtuemart', 'type' => 'component', 'name' => 'VirtueMart'],
        'hikashop'       => ['element' => 'com_hikashop', 'type' => 'component', 'name' => 'HikaShop'],
        'j2commerce'     => ['element' => 'com_j2commerce', 'type' => 'component', 'name' => 'J2Commerce'],
        'akeebabackup'   => ['element' => 'com_akeebabackup', 'type' => 'component', 'name' => 'Akeeba Backup'],
        'admintools'     => ['element' => 'com_admintools', 'type' => 'component', 'name' => 'Admin Tools'],
        'sh404sef'       => ['element' => 'com_sh404sef', 'type' => 'component', 'name' => 'sh404SEF'],
        'jce'            => ['element' => 'com_jce', 'type' => 'component', 'name' => 'JCE Editor'],
        'rsform'         => ['element' => 'com_rsform', 'type' => 'component', 'name' => 'RSForm! Pro'],
        'acymailing'     => ['element' => 'com_acym', 'type' => 'component', 'name' => 'AcyMailing'],
    ];

    /** @var array<string, bool>|null */
    private ?array $cache = null;

    public function isInstalled(string $key): bool
    {
        return ($this->detectAll()[$key] ?? false);
    }

    /** @return array<string, bool> */
    public function detectAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $db = Factory::getDbo();
        $result = [];

        foreach (self::INTEGRATIONS as $key => $def) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($def['element']))
                ->where($db->quoteName('type') . ' = ' . $db->quote($def['type']))
                ->where($db->quoteName('enabled') . ' = 1');

            $result[$key] = (int) $db->setQuery($query)->loadResult() > 0;
        }

        // Helix may use different element names
        if (!$result['helixultimate']) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('template'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->where('(' . $db->quoteName('element') . ' LIKE ' . $db->quote('%helix%')
                    . ' OR ' . $db->quoteName('name') . ' LIKE ' . $db->quote('%Helix%') . ')');
            $result['helixultimate'] = (int) $db->setQuery($query)->loadResult() > 0;
        }

        $this->cache = $result;
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function getInstalledList(): array
    {
        $detected = $this->detectAll();
        $list = [];

        foreach (self::INTEGRATIONS as $key => $def) {
            if ($detected[$key] ?? false) {
                $list[] = ['key' => $key, 'name' => $def['name'], 'element' => $def['element']];
            }
        }

        return $list;
    }

    /** @return string[] */
    public function getInstalledShops(): array
    {
        $shops = [];
        $detected = $this->detectAll();

        foreach (['virtuemart', 'hikashop', 'j2commerce'] as $shop) {
            if ($detected[$shop] ?? false) {
                $shops[] = $shop;
            }
        }

        return $shops;
    }
}
