<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Contenthistory;

class VersionExecutor
{
    public function listArticleVersions(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();

        if (!$this->tableExists()) {
            return ['versions' => [], 'message' => 'Content history not available.'];
        }

        $query = $db->getQuery(true)
            ->select(['version_id', 'save_date', 'editor_user_id', 'character_count', 'sha1_hash', 'version_note', 'keep_forever'])
            ->from($db->quoteName('#__history'))
            ->where($db->quoteName('ucm_type_id') . ' = ' . $this->getContentTypeId())
            ->where($db->quoteName('ucm_item_id') . ' = ' . $id)
            ->order('save_date DESC');

        return ['article_id' => $id, 'versions' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getArticleVersion(array $params): array
    {
        $versionId = (int) ($params['version_id'] ?? 0);
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__history'))
            ->where($db->quoteName('version_id') . ' = ' . $versionId);

        $row = $db->setQuery($query)->loadAssoc();
        if (!$row) {
            throw new \RuntimeException('Version not found.');
        }

        $row['data'] = json_decode($row['version_data'] ?? '{}', true);

        return $row;
    }

    public function restoreArticleVersion(array $params): array
    {
        $versionId = (int) ($params['version_id'] ?? 0);
        $version = $this->getArticleVersion(['version_id' => $versionId]);
        $data = $version['data'] ?? [];

        if (empty($data)) {
            throw new \RuntimeException('Version data is empty.');
        }

        $table = new Content(Factory::getDbo());
        $articleId = (int) ($data['id'] ?? $version['ucm_item_id'] ?? 0);

        if (!$table->load($articleId)) {
            throw new \RuntimeException('Article not found for restore.');
        }

        foreach (['title', 'alias', 'introtext', 'fulltext', 'catid', 'state', 'metadesc', 'metakey'] as $field) {
            if (isset($data[$field])) {
                $table->$field = $data[$field];
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to restore version.');
        }

        return ['article_id' => $articleId, 'version_id' => $versionId, 'message' => 'Version restored.'];
    }

    public function deleteArticleVersion(array $params): array
    {
        $versionId = (int) ($params['version_id'] ?? 0);
        $table = new Contenthistory(Factory::getDbo());

        if (!$table->delete($versionId)) {
            throw new \RuntimeException('Failed to delete version.');
        }

        return ['version_id' => $versionId, 'message' => 'Version deleted.'];
    }

    public function keepArticleVersion(array $params): array
    {
        $versionId = (int) ($params['version_id'] ?? 0);
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__history'))
            ->set($db->quoteName('keep_forever') . ' = 1')
            ->where($db->quoteName('version_id') . ' = ' . $versionId);

        $db->setQuery($query)->execute();

        return ['version_id' => $versionId, 'message' => 'Version marked keep forever.'];
    }

    private function tableExists(): bool
    {
        return in_array(
            Factory::getDbo()->getPrefix() . 'history',
            Factory::getDbo()->getTableList() ?: [],
            true
        );
    }

    private function getContentTypeId(): int
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('type_id')
            ->from($db->quoteName('#__content_types'))
            ->where($db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article'));
        return (int) $db->setQuery($query)->loadResult();
    }
}
