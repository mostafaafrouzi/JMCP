<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Jmcp\Administrator\Service\PathGuard;

class SeoExecutor
{
    public function analyzePageSeo(array $params): array
    {
        $articleId = (int) ($params['article_id'] ?? 0);
        $table = new Content(Factory::getDbo());

        if (!$table->load($articleId)) {
            throw new \RuntimeException('Article not found.');
        }

        $title = (string) $table->title;
        $metaDesc = (string) $table->metadesc;
        $intro = strip_tags((string) $table->introtext);
        $issues = [];
        $score = 100;

        if (strlen($title) < 30) {
            $issues[] = 'Title is too short (< 30 chars).';
            $score -= 15;
        } elseif (strlen($title) > 65) {
            $issues[] = 'Title is too long (> 65 chars).';
            $score -= 10;
        }

        if ($metaDesc === '') {
            $issues[] = 'Missing meta description.';
            $score -= 20;
        } elseif (strlen($metaDesc) < 120) {
            $issues[] = 'Meta description is short (< 120 chars).';
            $score -= 10;
        } elseif (strlen($metaDesc) > 160) {
            $issues[] = 'Meta description is too long (> 160 chars).';
            $score -= 5;
        }

        if (!preg_match('/<h1[^>]*>/i', (string) $table->introtext)) {
            $issues[] = 'No H1 tag found in introtext.';
            $score -= 10;
        }

        $images = preg_match_all('/<img[^>]+alt=["\']([^"\']*)["\']/', (string) $table->introtext, $m);
        if ($images && in_array('', $m[1], true)) {
            $issues[] = 'Some images missing alt text.';
            $score -= 10;
        }

        return [
            'article_id'      => $articleId,
            'title'           => $title,
            'title_length'    => strlen($title),
            'meta_description'=> $metaDesc,
            'meta_length'     => strlen($metaDesc),
            'word_count'      => str_word_count($intro),
            'seo_score'       => max(0, $score),
            'issues'          => $issues,
            'recommendations' => $this->recommendations($issues),
        ];
    }

    public function updateArticleSeoMeta(array $params): array
    {
        $id = (int) ($params['article_id'] ?? 0);
        $table = new Content(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException('Article not found.');
        }

        foreach (['metadesc', 'metakey', 'title'] as $field) {
            if (isset($params[$field])) {
                $table->$field = (string) $params[$field];
            }
        }

        if (isset($params['robots'])) {
            $metadata = json_decode($table->metadata ?? '{}', true) ?: [];
            $metadata['robots'] = (string) $params['robots'];
            $table->metadata = json_encode($metadata);
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update SEO meta.');
        }

        return ['article_id' => $id, 'message' => 'SEO metadata updated.'];
    }

    public function bulkUpdateMeta(array $params): array
    {
        $items = (array) ($params['items'] ?? []);
        $updated = 0;

        foreach ($items as $item) {
            if (!isset($item['article_id'])) {
                continue;
            }
            $this->updateArticleSeoMeta($item);
            $updated++;
        }

        return ['updated' => $updated, 'message' => "Updated {$updated} articles."];
    }

    public function suggestInternalLinks(array $params): array
    {
        $articleId = (int) ($params['article_id'] ?? 0);
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select(['id', 'title', 'alias', 'catid'])
            ->from('#__content')
            ->where('state = 1')
            ->where('id != ' . $articleId)
            ->order('hits DESC');

        $db->setQuery($query, 0, 10);
        $candidates = $db->loadAssocList() ?: [];

        foreach ($candidates as &$c) {
            $c['suggested_link'] = 'index.php?option=com_content&view=article&id=' . $c['id'];
        }

        return ['article_id' => $articleId, 'suggestions' => $candidates];
    }

    public function auditDuplicateContent(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['title', 'COUNT(*) AS cnt', 'GROUP_CONCAT(id) AS ids'])
            ->from('#__content')
            ->where('state >= 0')
            ->group('title')
            ->having('cnt > 1');

        return ['duplicates' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getSitemapStatus(array $params): array
    {
        $guard = new PathGuard();
        $robots = '';
        $sitemapUrls = [];

        try {
            $robots = (string) file_get_contents($guard->resolve('robots.txt'));
        } catch (\Throwable $e) {
        }

        foreach (['sitemap.xml', 'index.php?option=com_jmap'] as $candidate) {
            $sitemapUrls[] = Uri::root() . ltrim($candidate, '/');
        }

        return [
            'robots_txt'   => $robots,
            'sitemap_urls' => $sitemapUrls,
            'sef_enabled'  => (bool) Factory::getApplication()->getConfig()->get('sef'),
        ];
    }

    public function checkBrokenLinks(array $params): array
    {
        $limit = max(1, min(50, (int) ($params['limit'] ?? 20)));
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'introtext', $db->quoteName('fulltext')])
            ->from('#__content')
            ->where('state = 1')
            ->order('id DESC');

        $articles = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];
        $broken = [];

        foreach ($articles as $article) {
            $html = $article->introtext . $article->fulltext;
            if (preg_match_all('/href=["\']([^"\']+)["\']/', $html, $matches)) {
                foreach ($matches[1] as $url) {
                    if (str_starts_with($url, 'http') && str_contains($url, 'localhost')) {
                        $broken[] = ['article_id' => $article->id, 'url' => $url, 'reason' => 'localhost link'];
                    }
                }
            }
        }

        return ['checked_articles' => count($articles), 'potential_issues' => $broken];
    }

    public function getRedirectRules(array $params): array
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $prefix = $db->getPrefix();

        if (in_array($prefix . 'sh404sef_urls', $tables, true)) {
            $query = $db->getQuery(true)
                ->select(['id', 'oldurl', 'newurl', 'rank', 'dateadd'])
                ->from('#__sh404sef_urls')
                ->order('dateadd DESC');
            $db->setQuery($query, 0, 50);
            return ['source' => 'sh404SEF', 'rules' => $db->loadAssocList() ?: []];
        }

        return ['source' => 'none', 'rules' => [], 'message' => 'No redirect extension table found.'];
    }

    public function listJoomlaRedirects(array $params): array
    {
        $db = Factory::getDbo();
        $limit = max(1, min(100, (int) ($params['limit'] ?? 50)));

        $query = $db->getQuery(true)
            ->select(['id', 'old_url', 'new_url', 'published', 'created_date', 'modified_date', 'referer', 'hits'])
            ->from($db->quoteName('#__redirect_links'))
            ->order('id DESC');

        if (isset($params['published'])) {
            $query->where($db->quoteName('published') . ' = ' . (int) $params['published']);
        }

        return ['redirects' => $db->setQuery($query, 0, $limit)->loadAssocList() ?: []];
    }

    public function createJoomlaRedirect(array $params): array
    {
        $oldUrl = trim((string) ($params['old_url'] ?? ''));
        $newUrl = trim((string) ($params['new_url'] ?? ''));

        if ($oldUrl === '' || $newUrl === '') {
            throw new \RuntimeException('old_url and new_url are required.');
        }

        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        $row = (object) [
            'old_url'       => $oldUrl,
            'new_url'       => $newUrl,
            'published'     => (int) ($params['published'] ?? 1),
            'created_date'  => $now,
            'modified_date' => $now,
            'referer'       => '',
            'comment'       => (string) ($params['comment'] ?? 'Created via JMCP'),
            'hits'          => 0,
        ];

        $db->insertObject('#__redirect_links', $row);

        return [
            'id'      => (int) $db->insertid(),
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'message' => 'Redirect created successfully.',
        ];
    }

    public function updateJoomlaRedirect(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);

        if ($id <= 0 || $fields === []) {
            throw new \RuntimeException('id and fields are required.');
        }

        $fields['id'] = $id;
        $fields['modified_date'] = Factory::getDate()->toSql();
        Factory::getDbo()->updateObject('#__redirect_links', (object) $fields, 'id');

        return ['id' => $id, 'message' => 'Redirect updated.'];
    }

    public function deleteJoomlaRedirect(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('id is required.');
        }

        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)->delete('#__redirect_links')->where('id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'Redirect deleted.'];
    }

    public function getSchemaorgForItem(array $params): array
    {
        $itemId = (int) ($params['item_id'] ?? 0);
        $context = (string) ($params['context'] ?? 'com_content.article');

        if ($itemId <= 0) {
            throw new \RuntimeException('item_id is required.');
        }

        $db = Factory::getDbo();
        if (!$this->tableExists('#__schemaorg')) {
            return ['item_id' => $itemId, 'schema' => null, 'message' => 'Schema.org not available.'];
        }

        $row = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__schemaorg')
                ->where('itemId = ' . $itemId)->where('context = ' . $db->quote($context))
        )->loadAssoc();

        if ($row && !empty($row['schema'])) {
            $row['schema'] = json_decode((string) $row['schema'], true) ?: $row['schema'];
        }

        return ['item_id' => $itemId, 'context' => $context, 'record' => $row];
    }

    public function updateSchemaorgForItem(array $params): array
    {
        $itemId = (int) ($params['item_id'] ?? 0);
        $context = (string) ($params['context'] ?? 'com_content.article');
        $schemaType = (string) ($params['schema_type'] ?? 'Article');
        $schema = $params['schema'] ?? null;

        if ($itemId <= 0 || $schema === null) {
            throw new \RuntimeException('item_id and schema are required.');
        }

        if (!$this->tableExists('#__schemaorg')) {
            throw new \RuntimeException('Schema.org table not found.');
        }

        $db = Factory::getDbo();
        $schemaJson = is_string($schema) ? $schema : json_encode($schema, JSON_UNESCAPED_UNICODE);
        $existingId = (int) $db->setQuery(
            $db->getQuery(true)->select('id')->from('#__schemaorg')
                ->where('itemId = ' . $itemId)->where('context = ' . $db->quote($context))
        )->loadResult();

        if ($existingId > 0) {
            $db->setQuery(
                $db->getQuery(true)->update('#__schemaorg')
                    ->set('schemaType = ' . $db->quote($schemaType))
                    ->set('schema = ' . $db->quote((string) $schemaJson))
                    ->where('id = ' . $existingId)
            )->execute();
            return ['id' => $existingId, 'message' => 'Schema.org updated.'];
        }

        $row = (object) [
            'itemId'     => $itemId,
            'context'    => $context,
            'schemaType' => $schemaType,
            'schema'     => (string) $schemaJson,
        ];
        $db->insertObject('#__schemaorg', $row);

        return ['id' => (int) $db->insertid(), 'message' => 'Schema.org created.'];
    }

    private function tableExists(string $table): bool
    {
        $db = Factory::getDbo();
        return in_array($db->replacePrefix($table), $db->getTableList() ?: [], true);
    }

    /** @param string[] $issues */
    private function recommendations(array $issues): array
    {
        $map = [
            'Title is too short' => 'Expand title to 30-65 characters with primary keyword.',
            'Missing meta description' => 'Add a compelling 120-160 char meta description.',
            'No H1 tag' => 'Add exactly one H1 in introtext matching the topic.',
            'images missing alt' => 'Add descriptive alt text to all images.',
        ];

        $recs = [];
        foreach ($issues as $issue) {
            foreach ($map as $key => $rec) {
                if (str_contains($issue, $key) || str_starts_with($issue, $key)) {
                    $recs[] = $rec;
                }
            }
        }
        return array_unique($recs);
    }
}
