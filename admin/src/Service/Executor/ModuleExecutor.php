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
}
