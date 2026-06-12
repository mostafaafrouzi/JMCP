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
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Content;
use Joomla\Component\Jmcp\Administrator\Service\HtmlSanitizer;

class ContentExecutor
{
    private HtmlSanitizer $sanitizer;

    public function __construct(?HtmlSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer();
    }

    public function listArticles(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([
                'a.id', 'a.title', 'a.alias', 'a.catid', 'a.state', 'a.language',
                'a.created', 'a.modified', 'a.hits', 'a.access', 'a.featured',
                'c.title AS category_title',
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->order('a.id DESC');

        if (!empty($params['search'])) {
            $search = '%' . $db->escape(trim((string) $params['search']), true) . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE ' . $db->quote($search)
                . ' OR ' . $db->quoteName('a.alias') . ' LIKE ' . $db->quote($search) . ')');
        }

        if (isset($params['catid'])) {
            $query->where($db->quoteName('a.catid') . ' = ' . (int) $params['catid']);
        }

        if (isset($params['state'])) {
            $query->where($db->quoteName('a.state') . ' = ' . (int) $params['state']);
        }

        $limit  = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $db->setQuery($query, $offset, $limit);

        return [
            'articles' => $db->loadAssocList() ?: [],
            'limit'    => $limit,
            'offset'   => $offset,
        ];
    }

    public function getArticle(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $raw = (bool) ($params['raw_content'] ?? true);

        if ($raw) {
            $article = $this->loadArticleFromDatabase($id);
            if ($article === null) {
                throw new \RuntimeException(sprintf('Article %d not found.', $id));
            }
            $article['content_source'] = 'database';
            return $article;
        }

        $table = new Content(Factory::getDbo());
        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Article %d not found.', $id));
        }

        $props = $table->getProperties();
        $props['content_source'] = 'table';
        return $props;
    }

    /**
     * Read article directly from DB — avoids content plugins stripping HTML on load.
     *
     * @return array<string, mixed>|null
     */
    private function loadArticleFromDatabase(int $id): ?array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $id);

        $row = $db->setQuery($query)->loadAssoc();

        return $row ?: null;
    }

    public function createArticle(array $params): array
    {
        $table = new Content(Factory::getDbo());
        $user  = Factory::getUser();
        $title = trim((string) ($params['title'] ?? ''));

        $data = [
            'title'      => $title,
            'alias'      => OutputFilter::stringURLSafe($title),
            'catid'      => (int) ($params['catid'] ?? 0),
            'introtext'  => $this->sanitizer->textToHtml((string) ($params['introtext'] ?? '')),
            'fulltext'   => $this->sanitizer->textToHtml((string) ($params['fulltext'] ?? '')),
            'state'      => (int) ($params['state'] ?? 1),
            'language'   => (string) ($params['language'] ?? '*'),
            'access'     => 1,
            'created_by' => $user->id ?: 0,
            'modified_by'=> $user->id ?: 0,
            'publish_up' => Factory::getDate()->toSql(),
        ];

        if (!$table->save($data)) {
            throw new \RuntimeException('Failed to create article: ' . $table->getError());
        }

        return [
            'id'      => (int) $table->id,
            'title'   => $table->title,
            'message' => 'Article created successfully.',
        ];
    }

    public function updateArticle(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Content(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Article %d not found.', $id));
        }

        foreach ($fields as $key => $value) {
            if (property_exists($table, $key)) {
                if (in_array($key, ['introtext', 'fulltext'], true) && is_string($value)) {
                    $table->$key = $this->sanitizer->textToHtml($value);
                } else {
                    $table->$key = $value;
                }
            }
        }

        if (isset($fields['title']) && !isset($fields['alias'])) {
            $table->alias = OutputFilter::stringURLSafe((string) $fields['title']);
        }

        $table->modified    = Factory::getDate()->toSql();
        $table->modified_by = Factory::getUser()->id ?: 0;

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update article: ' . $table->getError());
        }

        return [
            'id'      => $id,
            'message' => 'Article updated successfully.',
        ];
    }

    public function deleteArticle(array $params): array
    {
        $id            = (int) ($params['id'] ?? 0);
        $force         = (bool) ($params['force'] ?? false);
        $expectedTitle = isset($params['expected_title']) ? trim((string) $params['expected_title']) : '';

        $article = $this->loadArticleFromDatabase($id);
        if ($article === null) {
            throw new \RuntimeException(sprintf('Article %d not found.', $id));
        }

        if ($expectedTitle !== '' && strcasecmp((string) ($article['title'] ?? ''), $expectedTitle) !== 0) {
            throw new \RuntimeException(sprintf(
                'Title mismatch. Expected "%s", found "%s". Aborting delete for safety.',
                $expectedTitle,
                (string) ($article['title'] ?? '')
            ));
        }

        $table = new Content(Factory::getDbo());
        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Article %d not found.', $id));
        }

        if ($force) {
            if ((int) ($article['state'] ?? 0) !== -2) {
                throw new \RuntimeException('Article must be in trash (state=-2) before permanent deletion. Trash it first with force=false.');
            }

            if (!$table->delete($id)) {
                throw new \RuntimeException('Failed to permanently delete article.');
            }

            return [
                'id'      => $id,
                'title'   => $article['title'] ?? '',
                'message' => 'Article permanently deleted.',
            ];
        }

        $table->state = -2;
        if (!$table->store()) {
            throw new \RuntimeException('Failed to move article to trash.');
        }

        return [
            'id'      => $id,
            'title'   => $article['title'] ?? '',
            'message' => 'Article moved to trash. Use force=true with expected_title for permanent deletion.',
        ];
    }

    public function listCategories(array $params): array
    {
        $db = Factory::getDbo();
        $extension = (string) ($params['extension'] ?? 'com_content');
        $limit  = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'alias', 'parent_id', 'level', 'published', 'extension', 'language', 'description'])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote($extension))
            ->order('lft ASC');

        $db->setQuery($query, $offset, $limit);

        return [
            'categories' => $db->loadAssocList() ?: [],
            'limit'      => $limit,
            'offset'     => $offset,
        ];
    }

    public function getCategory(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Category(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Category %d not found.', $id));
        }

        return $table->getProperties();
    }

    public function createCategory(array $params): array
    {
        $table = new Category(Factory::getDbo());
        $title = trim((string) ($params['title'] ?? ''));
        $parentId = (int) ($params['parent_id'] ?? 1);

        $data = [
            'title'      => $title,
            'alias'      => OutputFilter::stringURLSafe($title),
            'parent_id'  => $parentId,
            'extension'  => (string) ($params['extension'] ?? 'com_content'),
            'published'  => 1,
            'access'     => 1,
            'language'   => '*',
            'created_user_id' => Factory::getUser()->id ?: 0,
        ];

        $table->setLocation($parentId, 'last-child');

        if (!$table->save($data)) {
            throw new \RuntimeException('Failed to create category: ' . $table->getError());
        }

        return [
            'id'      => (int) $table->id,
            'title'   => $table->title,
            'message' => 'Category created successfully.',
        ];
    }

    public function updateCategory(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Category(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Category %d not found.', $id));
        }

        foreach ($fields as $key => $value) {
            if (property_exists($table, $key)) {
                $table->$key = $value;
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update category: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Category updated successfully.'];
    }

    public function deleteCategory(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Category(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException(sprintf('Category %d not found.', $id));
        }

        if (!$table->delete($id)) {
            throw new \RuntimeException('Failed to delete category: ' . $table->getError());
        }

        return ['id' => $id, 'message' => 'Category deleted successfully.'];
    }
}
