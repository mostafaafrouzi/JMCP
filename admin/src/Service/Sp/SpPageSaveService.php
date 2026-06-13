<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Sp;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Persists SP pages through the official Editor model when available.
 */
class SpPageSaveService
{
    /** @param array<string, mixed> $data */
    public function save(int $pageId, array $data): array
    {
        $db = Factory::getDbo();
        $existing = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__sppagebuilder')->where('id = ' . $pageId)
        )->loadAssoc();

        if (!$existing) {
            throw new \RuntimeException('SP page not found: ' . $pageId);
        }

        $payload = array_merge($existing, $data);
        $payload['id'] = $pageId;
        $payload['modified'] = Factory::getDate()->toSql();
        $payload['modified_by'] = Factory::getApplication()->getIdentity()->id ?: 0;

        if ($this->saveViaDatabase($pageId, $payload)) {
            return ['page_id' => $pageId, 'method' => 'database', 'message' => 'Saved via database.'];
        }

        if ($this->saveViaEditorModel($payload)) {
            return ['page_id' => $pageId, 'method' => 'editor_model', 'message' => 'Saved via SP Editor model.'];
        }

        throw new \RuntimeException('Failed to save SP page.');
    }

    public function saveCssOnly(int $pageId, string $css): array
    {
        $db = Factory::getDbo();
        $exists = $db->setQuery(
            $db->getQuery(true)->select('id')->from('#__sppagebuilder')->where('id = ' . $pageId)
        )->loadResult();

        if (!$exists) {
            throw new \RuntimeException('SP page not found: ' . $pageId);
        }

        $row = (object) [
            'id'          => $pageId,
            'css'         => $css,
            'modified'    => Factory::getDate()->toSql(),
            'modified_by' => Factory::getApplication()->getIdentity()->id ?: 0,
        ];
        $db->updateObject('#__sppagebuilder', $row, 'id');

        return [
            'page_id' => $pageId,
            'method'  => 'database',
            'bytes'   => strlen($css),
            'message' => 'CSS saved (css column only).',
        ];
    }

    /** @param array<string, mixed> $payload */
    private function saveViaDatabase(int $pageId, array $payload): bool
    {
        try {
            $db = Factory::getDbo();
            unset($payload['extension_view'], $payload['extension'], $payload['view_id'], $payload['created_on'], $payload['created_by']);
            $allowed = ['id', 'title', 'content', 'text', 'css', 'published', 'access', 'language', 'og_title', 'og_description', 'modified', 'modified_by'];
            $update = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $payload)) {
                    $update[$key] = $payload[$key];
                }
            }

            $row = (object) $update;
            $db->updateObject('#__sppagebuilder', $row, 'id');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $payload */
    private function saveViaEditorModel(array $payload): bool
    {
        $editorFile = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/models/editor.php';
        if (!is_file($editorFile)) {
            return false;
        }

        try {
            BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/models');
            /** @var \SppagebuilderModelEditor $model */
            $model = BaseDatabaseModel::getInstance('Editor', 'SppagebuilderModel', ['ignore_request' => true]);
            if ($model === false) {
                return false;
            }
            $model->savePage($payload);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
