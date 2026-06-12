<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
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
                'uri'         => 'joomla://config/global',
                'name'        => 'Global Configuration',
                'description' => 'Safe subset of Joomla global config',
                'mimeType'    => 'application/json',
            ],
        ];
    }

    public function readResource(string $uri): array
    {
        return match ($uri) {
            'joomla://site/info' => $this->siteInfo(),
            'joomla://extensions/installed' => $this->extensions(),
            'joomla://integrations/detected' => ['integrations' => $this->detector->getInstalledList()],
            'joomla://template/active' => $this->activeTemplate(),
            'joomla://config/global' => $this->globalConfig(),
            default => throw new \RuntimeException('Resource not found: ' . $uri),
        };
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
