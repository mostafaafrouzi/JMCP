<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;

class SiteExecutor
{
    public function getSiteInfo(array $params): array
    {
        $version = new Version();
        $config  = Factory::getApplication()->getConfig();
        $db      = Factory::getDbo();

        return [
            'joomla_version'  => $version->getShortVersion(),
            'php_version'     => PHP_VERSION,
            'site_name'     => (string) $config->get('sitename'),
            'site_url'      => Uri::root(),
            'admin_url'     => Uri::root() . 'administrator/',
            'database_type' => $db->name,
            'database_prefix' => $db->getPrefix(),
            'default_language' => (string) $config->get('language'),
            'debug_mode'    => (bool) $config->get('debug'),
            'timezone'      => (string) $config->get('offset'),
            'installed_languages' => LanguageHelper::getInstalledLanguages(0),
            'mcp_endpoint'  => Uri::root() . 'index.php?option=com_jmcp&task=rpc.handle',
            'sse_endpoint'  => Uri::root() . 'index.php?option=com_jmcp&task=rpc.handle',
        ];
    }

    public function listExtensions(array $params): array
    {
        $db = Factory::getDbo();
        $type = (string) ($params['type'] ?? '');

        $query = $db->getQuery(true)
            ->select([
                'extension_id AS id', 'name', 'type', 'element', 'folder',
                'enabled', 'access', 'manifest_cache', 'params',
            ])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' IN (' . implode(',', [
                $db->quote('component'),
                $db->quote('module'),
                $db->quote('plugin'),
                $db->quote('template'),
                $db->quote('package'),
                $db->quote('library'),
            ]) . ')')
            ->order('type ASC, name ASC');

        if ($type !== '') {
            $query->where($db->quoteName('type') . ' = ' . $db->quote($type));
        }

        $extensions = $db->setQuery($query)->loadAssocList() ?: [];

        foreach ($extensions as &$ext) {
            if (!empty($ext['manifest_cache'])) {
                $manifest = json_decode((string) $ext['manifest_cache'], true);
                $ext['version'] = $manifest['version'] ?? '';
            }
            unset($ext['params'], $ext['manifest_cache']);
        }

        return ['extensions' => $extensions];
    }

    public function listTemplateStyles(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'template', 'title', 'home', 'client_id', 'params'])
            ->from($db->quoteName('#__template_styles'))
            ->order('client_id ASC, template ASC');

        $styles = $db->setQuery($query)->loadAssocList() ?: [];

        return ['styles' => $styles];
    }

    public function listTags(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'alias', 'published', 'description', 'hits'])
            ->from($db->quoteName('#__tags'))
            ->order('title ASC');

        return ['tags' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getComponentParams(array $params): array
    {
        $option = (string) ($params['option'] ?? '');

        if ($option === '' || !str_starts_with($option, 'com_')) {
            throw new \RuntimeException('A valid component option is required (e.g. com_content).');
        }

        return [
            'option' => $option,
            'params' => ComponentHelper::getParams($option)->toArray(),
        ];
    }
}
