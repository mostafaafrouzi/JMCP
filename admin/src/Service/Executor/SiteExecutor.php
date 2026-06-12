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
use Joomla\Registry\Registry;

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

    public function updateComponentParams(array $params): array
    {
        $option = (string) ($params['option'] ?? '');
        $updates = (array) ($params['params'] ?? []);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($option === '' || !str_starts_with($option, 'com_') || $updates === []) {
            throw new \RuntimeException('option and params object are required.');
        }

        $db = Factory::getDbo();
        $row = $db->setQuery(
            $db->getQuery(true)->select(['extension_id', 'params'])
                ->from('#__extensions')
                ->where('element = ' . $db->quote($option))
                ->where('type = ' . $db->quote('component'))
        )->loadObject();

        if (!$row) {
            throw new \RuntimeException('Component not found: ' . $option);
        }

        $registry = new Registry($row->params ?? '');
        $registry->loadArray($updates);

        if ($dryRun) {
            return ['option' => $option, 'dry_run' => true, 'params' => $registry->toArray()];
        }

        $update = new \stdClass();
        $update->extension_id = (int) $row->extension_id;
        $update->params = $registry->toString();
        $db->updateObject('#__extensions', $update, 'extension_id');

        return ['option' => $option, 'params' => $registry->toArray(), 'message' => 'Component params updated.'];
    }

    public function toggleExtension(array $params): array
    {
        $extensionId = (int) ($params['extension_id'] ?? 0);
        $element = (string) ($params['element'] ?? '');
        $type = (string) ($params['type'] ?? 'component');
        $enabled = $params['enabled'] ?? null;

        if ($enabled === null) {
            throw new \RuntimeException('enabled (true/false) is required.');
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('extension_id')->from('#__extensions');

        if ($extensionId > 0) {
            $query->where('extension_id = ' . $extensionId);
        } elseif ($element !== '') {
            $query->where('element = ' . $db->quote($element))->where('type = ' . $db->quote($type));
        } else {
            throw new \RuntimeException('extension_id or element is required.');
        }

        $id = (int) $db->setQuery($query)->loadResult();
        if ($id <= 0) {
            throw new \RuntimeException('Extension not found.');
        }

        $update = new \stdClass();
        $update->extension_id = $id;
        $update->enabled = (int) ((bool) $enabled);
        $db->updateObject('#__extensions', $update, 'extension_id');

        return ['extension_id' => $id, 'enabled' => (bool) $enabled, 'message' => 'Extension state updated.'];
    }

    public function getGlobalConfig(array $params): array
    {
        $config = Factory::getApplication()->getConfig();

        return [
            'sitename'       => (string) $config->get('sitename'),
            'offline'        => (bool) $config->get('offline'),
            'offline_message'=> (string) $config->get('offline_message'),
            'language'       => (string) $config->get('language'),
            'sef'            => (bool) $config->get('sef'),
            'sef_rewrite'    => (bool) $config->get('sef_rewrite'),
            'caching'        => (int) $config->get('caching'),
            'cache_handler'  => (string) $config->get('cache_handler'),
            'gzip'           => (bool) $config->get('gzip'),
            'debug'          => (bool) $config->get('debug'),
            'offset'         => (string) $config->get('offset'),
            'mailfrom'       => (string) $config->get('mailfrom'),
            'fromname'       => (string) $config->get('fromname'),
            'meta_desc'      => (string) $config->get('MetaDesc'),
            'meta_keys'      => (string) $config->get('MetaKeys'),
        ];
    }

    public function updateGlobalConfig(array $params): array
    {
        $fields = $params['fields'] ?? null;
        if (!is_array($fields) || $fields === []) {
            throw new \RuntimeException('fields object is required.');
        }

        $dryRun = (bool) ($params['dry_run'] ?? false);
        $languageResult = null;

        if (isset($fields['language'])) {
            $languageResult = $this->updateComponentParams([
                'option'  => 'com_languages',
                'params'  => ['site' => (string) $fields['language']],
                'dry_run' => $dryRun,
            ]);
            unset($fields['language']);
        }

        if ($fields === []) {
            return [
                'dry_run'  => $dryRun,
                'updated'  => $languageResult ? ['language (com_languages.site)'] : [],
                'language' => $languageResult,
                'message'  => 'Global configuration updated.',
            ];
        }

        $allowed = [
            'sitename'        => 'string',
            'fromname'        => 'string',
            'MetaDesc'        => 'string',
            'MetaKeys'        => 'string',
            'mailfrom'        => 'string',
            'offline_message' => 'string',
            'offline'         => 'bool',
        ];

        $updates = [];
        foreach ($fields as $key => $value) {
            $key = (string) $key;
            if (!isset($allowed[$key])) {
                throw new \RuntimeException('Config key not allowed: ' . $key);
            }
            $updates[$key] = $allowed[$key] === 'bool' ? ($value ? 'true' : 'false') : (string) $value;
        }

        $path = JPATH_CONFIGURATION . '/configuration.php';
        if (!is_readable($path)) {
            throw new \RuntimeException('configuration.php is not readable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read configuration.php.');
        }

        $changed = [];
        foreach ($updates as $key => $value) {
            $pattern = '/public\s+\$' . preg_quote($key, '/') . '\s*=\s*[^;]+;/';
            $replacement = $allowed[$key] === 'bool'
                ? "public \${$key} = {$value};"
                : "public \${$key} = " . var_export($value, true) . ';';

            if (!preg_match($pattern, $content)) {
                throw new \RuntimeException('Config key not found in configuration.php: ' . $key);
            }

            $newContent = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count !== 1 || !is_string($newContent)) {
                throw new \RuntimeException('Failed to update configuration key: ' . $key);
            }

            $content = $newContent;
            $changed[] = $key;
        }

        if (!$dryRun) {
            if (!is_writable($path)) {
                throw new \RuntimeException('configuration.php is not writable.');
            }
            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException('Failed to write configuration.php.');
            }
        }

        $response = [
            'dry_run' => $dryRun,
            'updated' => $changed,
            'message' => $dryRun ? 'Dry run: configuration changes validated.' : 'Global configuration updated.',
        ];

        if ($languageResult !== null) {
            $response['language'] = $languageResult;
            $response['updated'][] = 'language (com_languages.site)';
        }

        return $response;
    }

    public function listBanners(array $params): array
    {
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

        $query = $db->getQuery(true)
            ->select(['id', 'name', 'alias', 'catid', 'state', 'clicks', 'impmade', 'sticky'])
            ->from($db->quoteName('#__banners'))
            ->order('id DESC');

        if (isset($params['state'])) {
            $query->where($db->quoteName('state') . ' = ' . (int) $params['state']);
        }

        return ['banners' => $db->setQuery($query, 0, $limit)->loadAssocList() ?: []];
    }

    public function listNewsfeeds(array $params): array
    {
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

        $query = $db->getQuery(true)
            ->select(['id', 'name', 'alias', 'catid', 'link', 'published', 'numarticles'])
            ->from($db->quoteName('#__newsfeeds'))
            ->order('id DESC');

        return ['feeds' => $db->setQuery($query, 0, $limit)->loadAssocList() ?: []];
    }
}
