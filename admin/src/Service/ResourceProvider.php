<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Component\Jmcp\Administrator\Service\Sp\SpAddonRegistry;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpPageTree;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpSectionLibrary;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;

class ResourceProvider
{
    private IntegrationDetector $detector;

    public function __construct(?IntegrationDetector $detector = null)
    {
        $this->detector = $detector ?? new IntegrationDetector();
    }

    /** @return array<int, array<string, mixed>> */
    public function listResources(): array
    {
        return [
            [
                'uri'         => 'joomla://site/info',
                'name'        => 'Site Information',
                'description' => 'Joomla version, URLs, languages, database prefix',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'joomla://extensions/installed',
                'name'        => 'Installed Extensions',
                'description' => 'All installed components, modules, plugins, templates',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'joomla://integrations/detected',
                'name'        => 'Detected Integrations',
                'description' => 'SP Page Builder, shops, Akeeba, etc.',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'joomla://template/active',
                'name'        => 'Active Template',
                'description' => 'Current site template style and params',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'joomla://sp/addons',
                'name'        => 'SP Page Builder Addons',
                'description' => 'List of installed SP addons (button, heading, image, …)',
                'mimeType'    => 'application/json',
            ],
            [
                'uri'         => 'joomla://sp/section-presets',
                'name'        => 'SP Section Presets',
                'description' => 'Row presets from existing SP pages (for sp_insert_section)',
                'mimeType'    => 'application/json',
            ],
        ];
    }

    public function readResource(string $uri): array
    {
        if (preg_match('#^joomla://sp/addon/([a-z0-9_\-]+)$#', $uri, $m)) {
            $registry = new SpAddonRegistry();
            if (!$registry->isInstalled()) {
                throw new \RuntimeException('SP Page Builder is not installed.');
            }

            return $registry->getAddonSchema($m[1]);
        }

        if (preg_match('#^joomla://sp/page/(\d+)/tree$#', $uri, $m)) {
            $tree = new SpPageTree();
            $db = Factory::getDbo();
            $content = $db->setQuery(
                $db->getQuery(true)->select('content')->from('#__sppagebuilder')->where('id = ' . (int) $m[1])
            )->loadResult();
            $rows = $tree->decode((string) $content);

            return [
                'page_id'   => (int) $m[1],
                'row_count' => count($rows),
                'nodes'     => $tree->buildTree($rows, true),
            ];
        }

        return match ($uri) {
            'joomla://site/info' => $this->siteInfo(),
            'joomla://extensions/installed' => $this->extensions(),
            'joomla://integrations/detected' => ['integrations' => $this->detector->getInstalledList()],
            'joomla://template/active' => $this->activeTemplate(),
            'joomla://config/global' => $this->globalConfig(),
            'joomla://sp/addons' => $this->spAddons(),
            'joomla://sp/section-presets' => $this->spSectionPresets(),
            default => throw new \RuntimeException('Resource not found: ' . $uri),
        };
    }

    private function spAddons(): array
    {
        $registry = new SpAddonRegistry();
        if (!$registry->isInstalled()) {
            return ['installed' => false, 'addons' => []];
        }

        return ['installed' => true, 'addons' => $registry->listAddons()];
    }

    private function spSectionPresets(): array
    {
        $library = new SpSectionLibrary();

        return ['presets' => $library->listPresets(30)];
    }

    private function siteInfo(): array
    {
        $v = new Version();
        $config = Factory::getApplication()->getConfig();
        return [
            'joomla_version' => $v->getShortVersion(),
            'site_name'      => (string) $config->get('sitename'),
            'site_url'       => Uri::root(),
            'language'       => (string) $config->get('language'),
            'database_prefix'=> Factory::getDbo()->getPrefix(),
        ];
    }

    private function extensions(): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['name', 'type', 'element', 'enabled'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' IN (' . implode(',', [
                $db->quote('component'), $db->quote('module'), $db->quote('plugin'), $db->quote('template'),
            ]) . ')')
            ->order('type, name');
        return ['extensions' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    private function activeTemplate(): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('home') . ' = 1');
        return ['template' => $db->setQuery($query)->loadAssoc() ?: []];
    }

    private function globalConfig(): array
    {
        $config = Factory::getApplication()->getConfig();
        return [
            'sitename'      => (string) $config->get('sitename'),
            'mailonline'    => (bool) $config->get('mailonline'),
            'cache_handler' => (string) $config->get('cache_handler'),
            'debug'         => (bool) $config->get('debug'),
            'sef'           => (bool) $config->get('sef'),
            'robots'        => (string) $config->get('robots'),
        ];
    }
}
