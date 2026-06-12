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
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\MenuType;

class MenuExecutor
{
    public function listMenus(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'menutype', 'title', 'description', 'client_id'])
            ->from($db->quoteName('#__menu_types'))
            ->order('title ASC');

        return ['menus' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function listMenuItems(array $params): array
    {
        $db = Factory::getDbo();
        $clientId = (($params['client'] ?? 'site') === 'administrator') ? 1 : 0;

        $query = $db->getQuery(true)
            ->select([
                'id', 'title', 'alias', 'menutype', 'link', 'type', 'published',
                'parent_id', 'level', 'component_id', 'home', 'language', 'access',
            ])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = ' . $clientId)
            ->order('lft ASC');

        if (!empty($params['menutype'])) {
            $query->where($db->quoteName('menutype') . ' = ' . $db->quote((string) $params['menutype']));
        }

        return ['items' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getMenuItem(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Menu(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Menu item %d not found.', $id));
        }

        return $table->getProperties();
    }

    public function createMenuItem(array $params): array
    {
        $table = new Menu(Factory::getDbo());
        $parentId = (int) ($params['parent_id'] ?? 1);

        $data = [
            'title'        => (string) ($params['title'] ?? ''),
            'menutype'     => (string) ($params['menutype'] ?? ''),
            'type'         => (string) ($params['type'] ?? 'component'),
            'link'         => (string) ($params['link'] ?? ''),
            'published'    => 1,
            'access'       => 1,
            'language'     => '*',
            'client_id'    => 0,
            'component_id' => (int) ($params['component_id'] ?? 0),
        ];

        $table->setLocation($parentId, 'last-child');

        if (!$table->save($data)) {
            throw new \RuntimeException('Failed to create menu item: ' . $table->getError());
        }

        return [
            'id'      => (int) $table->id,
            'title'   => $table->title,
            'message' => 'Menu item created successfully.',
        ];
    }

    public function updateMenuItem(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Menu(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Menu item %d not found.', $id));
        }

        foreach ($fields as $key => $value) {
            if (property_exists($table, $key)) {
                $table->$key = $value;
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update menu item: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Menu item updated successfully.'];
    }

    public function deleteMenuItem(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Menu(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Menu item %d not found.', $id));
        }

        if (!$table->delete($id)) {
            throw new \RuntimeException('Failed to delete menu item: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Menu item deleted successfully.'];
    }
}
