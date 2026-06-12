<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class PluginExecutor
{
    public function listPlugins(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([
                'extension_id AS id', 'name', 'element', 'folder', 'enabled',
                'access', 'ordering', 'manifest_cache',
            ])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->order('folder ASC, ordering ASC');

        if (!empty($params['folder'])) {
            $query->where($db->quoteName('folder') . ' = ' . $db->quote((string) $params['folder']));
        }

        if (!empty($params['element'])) {
            $query->where($db->quoteName('element') . ' = ' . $db->quote((string) $params['element']));
        }

        return ['plugins' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function togglePluginState(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $enabled = (bool) ($params['enabled'] ?? false);

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = ' . ($enabled ? 1 : 0))
            ->where($db->quoteName('extension_id') . ' = ' . $id)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() === 0) {
            throw new \RuntimeException(sprintf('Plugin %d not found.', $id));
        }

        return [
            'id'      => $id,
            'enabled' => $enabled,
            'message' => $enabled ? 'Plugin enabled.' : 'Plugin disabled.',
        ];
    }

    public function updatePluginParams(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $newParams = (array) ($params['params'] ?? []);

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['params'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . $id)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $current = $db->setQuery($query)->loadResult();

        if ($current === null) {
            throw new \RuntimeException(sprintf('Plugin %d not found.', $id));
        }

        $registry = new Registry($current);
        $registry->loadArray($newParams);

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($registry->toString()))
            ->where($db->quoteName('extension_id') . ' = ' . $id);

        $db->setQuery($update)->execute();

        return ['id' => $id, 'message' => 'Plugin parameters updated.'];
    }
}
