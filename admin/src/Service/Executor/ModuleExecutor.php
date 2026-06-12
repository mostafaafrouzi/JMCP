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
use Joomla\CMS\Table\Module;
use Joomla\Registry\Registry;

class ModuleExecutor
{
    public function listModules(array $params): array
    {
        $db = Factory::getDbo();
        $clientId = (($params['client'] ?? 'site') === 'administrator') ? 1 : 0;

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'module', 'position', 'published', 'access', 'ordering', 'language'])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('client_id') . ' = ' . $clientId)
            ->order('ordering ASC');

        return ['modules' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getModule(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Module(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Module %d not found.', $id));
        }

        $props = $table->getProperties();
        $props['params'] = (new Registry($props['params'] ?? ''))->toArray();

        return $props;
    }

    public function createModule(array $params): array
    {
        $table = new Module(Factory::getDbo());
        $moduleType = (string) ($params['module'] ?? 'mod_custom');

        $registry = new Registry();
        if (!empty($params['params']) && is_array($params['params'])) {
            $registry->loadArray($params['params']);
        }

        if ($moduleType === 'mod_custom' && !empty($params['content'])) {
            $registry->set('content', (string) $params['content']);
            $registry->set('prepare_content', 1);
        }

        $data = [
            'title'     => (string) ($params['title'] ?? ''),
            'module'    => $moduleType,
            'position'  => (string) ($params['position'] ?? ''),
            'published' => (int) ($params['published'] ?? 1),
            'access'    => 1,
            'language'  => '*',
            'client_id' => 0,
            'params'    => $registry->toString(),
            'showtitle' => 1,
        ];

        if (!$table->save($data)) {
            throw new \RuntimeException('Failed to create module: ' . $table->getError());
        }

        return [
            'id'      => (int) $table->id,
            'title'   => $table->title,
            'message' => 'Module created successfully.',
        ];
    }

    public function updateModule(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Module(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Module %d not found.', $id));
        }

        if (isset($fields['params']) && is_array($fields['params'])) {
            $registry = new Registry($table->params ?? '');
            $registry->loadArray($fields['params']);
            $fields['params'] = $registry->toString();
        }

        if (isset($fields['content'])) {
            $registry = new Registry($table->params ?? '');
            $registry->set('content', (string) $fields['content']);
            $fields['params'] = $registry->toString();
            unset($fields['content']);
        }

        foreach ($fields as $key => $value) {
            if (property_exists($table, $key)) {
                $table->$key = $value;
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update module: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Module updated successfully.'];
    }

    public function deleteModule(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Module(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Module %d not found.', $id));
        }

        if (!$table->delete($id)) {
            throw new \RuntimeException('Failed to delete module: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Module deleted successfully.'];
    }

    public function assignModuleToMenu(array $params): array
    {
        $moduleId = (int) ($params['module_id'] ?? 0);
        $menuIds = array_map('intval', (array) ($params['menu_ids'] ?? []));
        $mode = (string) ($params['mode'] ?? 'replace');

        if ($moduleId <= 0) {
            throw new \RuntimeException('module_id is required.');
        }

        $db = Factory::getDbo();
        if ($mode === 'replace') {
            $db->setQuery(
                $db->getQuery(true)->delete('#__modules_menu')->where('moduleid = ' . $moduleId)
            )->execute();
        }

        if ($menuIds === []) {
            $db->insertObject('#__modules_menu', (object) ['moduleid' => $moduleId, 'menuid' => 0]);
        } else {
            foreach ($menuIds as $menuId) {
                if ($mode === 'add') {
                    $exists = (int) $db->setQuery(
                        $db->getQuery(true)->select('COUNT(*)')->from('#__modules_menu')
                            ->where('moduleid = ' . $moduleId)->where('menuid = ' . $menuId)
                    )->loadResult();
                    if ($exists > 0) {
                        continue;
                    }
                }
                $db->insertObject('#__modules_menu', (object) ['moduleid' => $moduleId, 'menuid' => $menuId]);
            }
        }

        return ['module_id' => $moduleId, 'menu_ids' => $menuIds ?: [0], 'message' => 'Module menu assignment updated.'];
    }

    public function updateUtArticlesModule(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Module id is required.');
        }

        $table = new Module(Factory::getDbo());
        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Module %d not found.', $id));
        }

        if ($table->module !== 'mod_ut_articles_pro') {
            throw new \RuntimeException('Module is not mod_ut_articles_pro.');
        }

        $registry = new Registry($table->params ?? '');
        $map = [
            'catid' => 'catid', 'count' => 'count', 'ordering' => 'ordering',
            'show_intro' => 'show_intro', 'show_author' => 'show_author',
            'show_category' => 'show_category', 'show_date' => 'show_date',
        ];
        foreach ($map as $param => $key) {
            if (isset($params[$param])) {
                $registry->set($key, $params[$param]);
            }
        }

        if (isset($params['params']) && is_array($params['params'])) {
            $registry->loadArray($params['params']);
        }

        $table->params = $registry->toString();
        foreach (['title', 'position', 'published'] as $field) {
            if (isset($params[$field])) {
                $table->$field = $params[$field];
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update UT articles module.');
        }

        return ['id' => $id, 'message' => 'UT articles module updated.'];
    }
}
