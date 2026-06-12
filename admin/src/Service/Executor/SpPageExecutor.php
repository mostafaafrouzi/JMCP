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
use Joomla\Component\Jmcp\Administrator\Service\Executor\MenuExecutor;

class SpPageExecutor
{
    private const TABLE = '#__sppagebuilder';

    public function listSpPages(array $params): array
    {
        $db = Factory::getDbo();

        if (!$this->tableExists()) {
            return [
                'installed' => false,
                'pages'     => [],
                'message'   => 'SP Page Builder is not installed on this site.',
            ];
        }

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'published', 'access', 'language', 'created', 'modified', 'hits'])
            ->from($db->quoteName(self::TABLE))
            ->order('id DESC');

        return [
            'installed' => true,
            'pages'     => $db->setQuery($query)->loadAssocList() ?: [],
        ];
    }

    public function getSpPage(array $params): array
    {
        $this->assertInstalled();
        $id = (int) ($params['id'] ?? 0);

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('id') . ' = ' . $id);

        $page = $db->setQuery($query)->loadAssoc();

        if (!$page) {
            throw new \RuntimeException(sprintf('SP Page %d not found.', $id));
        }

        return $page;
    }

    public function saveSpPage(array $params): array
    {
        $this->assertInstalled();

        $db = Factory::getDbo();
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();

        $data = [
            'title'     => (string) ($params['title'] ?? ''),
            'text'      => (string) ($params['layout'] ?? ''),
            'published' => (int) ($params['published'] ?? 1),
            'access'    => 1,
            'language'  => '*',
            'modified'  => $now,
            'modified_by' => $user->id ?: 0,
        ];

        if ($id > 0) {
            $data['id'] = $id;
            $row = (object) $data;
            $db->updateObject(self::TABLE, $row, 'id');
            $message = 'SP Page updated successfully.';
        } else {
            $data['created']    = $now;
            $data['created_by'] = $user->id ?: 0;
            $data['hits']       = 0;
            $row = (object) $data;
            $db->insertObject(self::TABLE, $row);
            $id = (int) $db->insertid();
            $message = 'SP Page created successfully.';
        }

        return [
            'id'      => $id,
            'title'   => $data['title'],
            'message' => $message,
        ];
    }

    public function duplicateSpPage(array $params): array
    {
        $source = $this->getSpPage(['id' => (int) ($params['id'] ?? 0)]);
        $title = (string) ($params['title'] ?? ($source['title'] ?? 'Copy') . ' (Copy)');

        return $this->saveSpPage([
            'title'     => $title,
            'layout'    => (string) ($source['text'] ?? ''),
            'published' => (int) ($source['published'] ?? 0),
        ]);
    }

    public function publishSpPageToMenu(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? 0);
        $page = $this->getSpPage(['id' => $pageId]);
        $menutype = (string) ($params['menutype'] ?? 'mainmenu');
        $title = (string) ($params['title'] ?? $page['title'] ?? 'SP Page');

        $menu = new MenuExecutor();
        return $menu->createMenuItem([
            'title'    => $title,
            'menutype' => $menutype,
            'type'     => 'component',
            'link'     => 'index.php?option=com_sppagebuilder&view=page&id=' . $pageId,
        ]);
    }

    private function tableExists(): bool
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $name = str_replace('#__', $db->getPrefix(), self::TABLE);

        return in_array($name, $tables, true);
    }

    private function assertInstalled(): void
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('SP Page Builder is not installed on this site.');
        }
    }
}
