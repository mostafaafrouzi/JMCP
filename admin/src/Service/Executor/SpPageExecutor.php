<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

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
            ->select([
                'id', 'title', 'published', 'access', 'language',
                'created_on', 'modified', 'hits',
                'CHAR_LENGTH(IFNULL(content, \'\')) AS content_length',
            ])
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
        $includeContent = (bool) ($params['include_content'] ?? true);

        $db = Factory::getDbo();
        $columns = ['id', 'title', 'published', 'access', 'language', 'text', 'css', 'og_title', 'og_description', 'created_on', 'modified', 'hits'];
        if ($includeContent) {
            $columns[] = 'content';
        } else {
            $columns[] = 'CHAR_LENGTH(IFNULL(content, \'\')) AS content_length';
        }

        $query = $db->getQuery(true)
            ->select($columns)
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
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($id <= 0 && empty($params['title'])) {
            throw new \RuntimeException('title is required when creating a new SP page.');
        }

        $data = [
            'modified'    => $now,
            'modified_by' => $user->id ?: 0,
        ];

        if (isset($params['title'])) {
            $data['title'] = (string) $params['title'];
        }
        if (isset($params['published'])) {
            $data['published'] = (int) $params['published'];
        }
        if (isset($params['language'])) {
            $data['language'] = (string) $params['language'];
        }
        if (array_key_exists('content', $params)) {
            $data['content'] = (string) $params['content'];
        }
        if (array_key_exists('layout', $params)) {
            $data['text'] = (string) $params['layout'];
        }
        if (array_key_exists('css', $params)) {
            $data['css'] = (string) $params['css'];
        }
        if (isset($params['og_title'])) {
            $data['og_title'] = (string) $params['og_title'];
        }
        if (isset($params['og_description'])) {
            $data['og_description'] = (string) $params['og_description'];
        }

        if ($id > 0) {
            if ($dryRun) {
                return ['id' => $id, 'dry_run' => true, 'fields' => array_keys($data), 'message' => 'Dry run: SP page update validated.'];
            }
            $data['id'] = $id;
            $db->updateObject(self::TABLE, (object) $data, 'id');
            $message = 'SP Page updated successfully.';
        } else {
            if ($dryRun) {
                return ['dry_run' => true, 'fields' => array_keys($data), 'message' => 'Dry run: SP page create validated.'];
            }
            $data['published'] = (int) ($params['published'] ?? 1);
            $data['access'] = 1;
            $data['language'] = (string) ($params['language'] ?? '*');
            $data['created_on'] = $now;
            $data['created_by'] = $user->id ?: 0;
            $data['hits'] = 0;
            $data['text'] = (string) ($params['layout'] ?? '[]');
            $data['content'] = (string) ($params['content'] ?? '[]');
            $db->insertObject(self::TABLE, (object) $data);
            $id = (int) $db->insertid();
            $message = 'SP Page created successfully.';
        }

        return [
            'id'      => $id,
            'title'   => $data['title'] ?? null,
            'message' => $message,
        ];
    }

    public function bulkReplaceSpContent(array $params): array
    {
        $this->assertInstalled();
        $maintenance = new MaintenanceExecutor();

        $targets = [self::TABLE => ['content', 'text', 'css', 'og_title', 'og_description']];
        $pageIds = $params['page_ids'] ?? null;

        if (is_array($pageIds) && $pageIds !== []) {
            return $this->bulkReplaceForPages($params, $pageIds);
        }

        return $maintenance->bulkContentReplace([
            'preset'       => 'sp_pages',
            'replacements' => $params['replacements'] ?? [],
            'dry_run'      => (bool) ($params['dry_run'] ?? false),
        ]);
    }

    /** @param array<int, int> $pageIds */
    private function bulkReplaceForPages(array $params, array $pageIds): array
    {
        $maintenance = new MaintenanceExecutor();
        $results = [];

        foreach ($pageIds as $pageId) {
            $pageId = (int) $pageId;
            $results[] = $maintenance->bulkContentReplace([
                'targets'      => [
                    self::TABLE => ['content', 'text', 'css', 'og_title', 'og_description'],
                ],
                'replacements' => $params['replacements'] ?? [],
                'dry_run'      => (bool) ($params['dry_run'] ?? false),
                'where'        => ['id' => $pageId],
            ]);
        }

        return ['page_ids' => $pageIds, 'results' => $results];
    }

    public function duplicateSpPage(array $params): array
    {
        $source = $this->getSpPage(['id' => (int) ($params['id'] ?? 0)]);
        $title = (string) ($params['title'] ?? ($source['title'] ?? 'Copy') . ' (Copy)');

        return $this->saveSpPage([
            'title'     => $title,
            'layout'    => (string) ($source['text'] ?? '[]'),
            'content'   => (string) ($source['content'] ?? '[]'),
            'css'       => (string) ($source['css'] ?? ''),
            'published' => (int) ($source['published'] ?? 0),
        ]);
    }

    public function deleteSpPage(array $params): array
    {
        $this->assertInstalled();
        $id = (int) ($params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($id <= 0) {
            throw new \RuntimeException('Page id is required.');
        }

        $db = Factory::getDbo();
        $exists = (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from(self::TABLE)->where('id = ' . $id)
        )->loadResult();

        if ($exists === 0) {
            throw new \RuntimeException('SP page not found.');
        }

        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'message' => 'Dry run: delete validated.'];
        }

        $db->setQuery($db->getQuery(true)->delete(self::TABLE)->where('id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'SP page deleted.'];
    }

    public function updateSpPageMeta(array $params): array
    {
        $this->assertInstalled();
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Page id is required.');
        }

        $allowed = ['title', 'published', 'access', 'language', 'og_title', 'og_description', 'og_image', 'attribs', 'extension', 'view_id'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $params)) {
                $data[$key] = $params[$key];
            }
        }

        if ($data === []) {
            throw new \RuntimeException('At least one meta field is required.');
        }

        if (isset($data['attribs']) && is_array($data['attribs'])) {
            $data['attribs'] = json_encode($data['attribs']);
        }

        $dryRun = (bool) ($params['dry_run'] ?? false);
        if ($dryRun) {
            return ['id' => $id, 'dry_run' => true, 'fields' => array_keys($data)];
        }

        $data['id'] = $id;
        $data['modified'] = Factory::getDate()->toSql();
        $data['modified_by'] = Factory::getApplication()->getIdentity()->id ?: 0;
        $row = (object) $data;
        Factory::getDbo()->updateObject(self::TABLE, $row, 'id');

        return ['id' => $id, 'message' => 'SP page meta updated.'];
    }

    public function listSpPageModules(array $params): array
    {
        $this->assertInstalled();
        $pageId = (int) ($params['page_id'] ?? 0);
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'position', 'published', 'params'])
            ->from('#__modules')
            ->where('module = ' . $db->quote('mod_sppagebuilder'))
            ->where('client_id = 0');

        if ($pageId > 0) {
            $query->where('params LIKE ' . $db->quote('%"id":' . $pageId . '%'));
        }

        $modules = $db->setQuery($query)->loadAssocList() ?: [];
        foreach ($modules as &$mod) {
            $decoded = json_decode((string) ($mod['params'] ?? ''), true);
            $mod['page_id'] = (int) ($decoded['id'] ?? 0);
            unset($mod['params']);
        }

        return ['modules' => $modules];
    }

    public function listSpCollections(array $params): array
    {
        $this->assertCollectionTable();
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'alias', 'published', 'access', 'language', 'created'])
            ->from('#__sppagebuilder_collections')
            ->order('id DESC');

        return ['collections' => $db->setQuery($query, 0, $limit)->loadAssocList() ?: []];
    }

    public function getSpCollection(array $params): array
    {
        $this->assertCollectionTable();
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $collection = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__sppagebuilder_collections')->where('id = ' . $id)
        )->loadAssoc();

        if (!$collection) {
            throw new \RuntimeException('SP collection not found.');
        }

        $items = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__sppagebuilder_collection_items')
                ->where('collection_id = ' . $id)->order('ordering ASC')
        )->loadAssocList() ?: [];

        return ['collection' => $collection, 'items' => $items];
    }

    public function saveSpCollection(array $params): array
    {
        $this->assertCollectionTable();
        $id = (int) ($params['id'] ?? 0);
        $title = trim((string) ($params['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('title is required.');
        }

        $db = Factory::getDbo();
        $data = [
            'title'     => $title,
            'alias'     => (string) ($params['alias'] ?? ''),
            'published' => (int) ($params['published'] ?? 1),
            'access'    => (int) ($params['access'] ?? 1),
            'language'  => (string) ($params['language'] ?? '*'),
        ];

        if ($id > 0) {
            $data['id'] = $id;
            $db->updateObject('#__sppagebuilder_collections', (object) $data, 'id');
        } else {
            $data['created'] = Factory::getDate()->toSql();
            $data['created_by'] = Factory::getApplication()->getIdentity()->id ?: 0;
            $db->insertObject('#__sppagebuilder_collections', (object) $data);
            $id = (int) $db->insertid();
        }

        return ['id' => $id, 'message' => 'SP collection saved.'];
    }

    public function deleteSpCollection(array $params): array
    {
        $this->assertCollectionTable();
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Collection id is required.');
        }

        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)->delete('#__sppagebuilder_collections')->where('id = ' . $id))->execute();
        $db->setQuery($db->getQuery(true)->delete('#__sppagebuilder_collection_items')->where('collection_id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'SP collection deleted.'];
    }

    public function publishSpPageToMenu(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? 0);
        $page = $this->getSpPage(['id' => $pageId, 'include_content' => false]);
        $menutype = (string) ($params['menutype'] ?? 'mainmenu');
        $title = (string) ($params['title'] ?? $page['title'] ?? 'SP Page');

        $db = Factory::getDbo();
        $componentId = (int) $db->setQuery(
            $db->getQuery(true)->select('extension_id')->from('#__extensions')
                ->where('element = ' . $db->quote('com_sppagebuilder'))
                ->where('type = ' . $db->quote('component'))
        )->loadResult();

        $menu = new MenuExecutor();

        return $menu->createMenuItem([
            'title'        => $title,
            'menutype'     => $menutype,
            'type'         => 'component',
            'link'         => 'index.php?option=com_sppagebuilder&view=page&id=' . $pageId,
            'component_id' => $componentId,
        ]);
    }

    private function assertCollectionTable(): void
    {
        $this->assertInstalled();
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $name = $db->getPrefix() . 'sppagebuilder_collections';
        if (!in_array($name, $tables, true)) {
            throw new \RuntimeException('SP Page Builder collections are not available on this site.');
        }
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
