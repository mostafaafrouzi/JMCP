<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;

class MultilingualExecutor
{
    public function listContentLanguages(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['lang_id', 'lang_code', 'title', 'title_native', 'sef', 'published', 'ordering'])
            ->from($db->quoteName('#__languages'))
            ->order('ordering ASC');

        return ['languages' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getContentLanguage(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_id') . ' = ' . $id);

        $lang = $db->setQuery($query)->loadAssoc();
        if (!$lang) {
            throw new \RuntimeException('Language not found.');
        }
        return $lang;
    }

    public function createContentLanguage(array $params): array
    {
        $db = Factory::getDbo();
        $data = (object) [
            'lang_code'    => (string) ($params['lang_code'] ?? ''),
            'title'        => (string) ($params['title'] ?? ''),
            'title_native' => (string) ($params['title_native'] ?? $params['title'] ?? ''),
            'sef'          => (string) ($params['sef'] ?? ''),
            'published'    => 1,
            'access'       => 1,
            'ordering'     => 1,
        ];

        $db->insertObject('#__languages', $data);
        return ['lang_id' => (int) $db->insertid(), 'message' => 'Language created.'];
    }

    public function updateContentLanguage(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $fields['lang_id'] = $id;

        Factory::getDbo()->updateObject('#__languages', (object) $fields, 'lang_id');
        return ['id' => $id, 'message' => 'Language updated.'];
    }

    public function listArticleAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        return ['associations' => $this->getAssociations('com_content.item', $id)];
    }

    public function setArticleAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $associations = (array) ($params['associations'] ?? []);

        $this->saveAssociations('com_content.item', $id, $associations);

        return ['id' => $id, 'associations' => $associations, 'message' => 'Article associations saved.'];
    }

    public function listMenuItemAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        return ['associations' => $this->getAssociations('com_menus.item', $id)];
    }

    public function setMenuItemAssociations(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $associations = (array) ($params['associations'] ?? []);

        $this->saveAssociations('com_menus.item', $id, $associations);

        return ['id' => $id, 'associations' => $associations, 'message' => 'Menu item associations saved.'];
    }

    /** @return array<string, int> */
    private function getAssociations(string $context, int $id): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['a.language', 'a.id'])
            ->from($db->quoteName('#__associations', 'ass'))
            ->join('INNER', $db->quoteName('#__associations', 'a2') . ' ON a2.key = ass.key')
            ->join('INNER', $this->getItemTable($context) . ' AS a ON a.id = a2.id')
            ->where('ass.id = ' . $id . ' AND ass.context = ' . $db->quote($context));

        $rows = $db->setQuery($query)->loadAssocList() ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[$row['language']] = (int) $row['id'];
        }
        return $result;
    }

    /** @param array<string, int> $associations lang => id */
    private function saveAssociations(string $context, int $id, array $associations): void
    {
        $db = Factory::getDbo();
        $key = $this->getAssociationKey($context, $id) ?? bin2hex(random_bytes(8));

        // Remove old
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__associations'))
            ->where('context = ' . $db->quote($context))
            ->where('key = ' . $db->quote($key));
        $db->setQuery($query)->execute();

        foreach ($associations as $lang => $itemId) {
            $db->insertObject('#__associations', (object) [
                'id'      => (int) $itemId,
                'context' => $context,
                'key'     => $key,
            ]);
        }
    }

    private function getAssociationKey(string $context, int $id): ?string
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('key')
            ->from($db->quoteName('#__associations'))
            ->where('id = ' . $id)
            ->where('context = ' . $db->quote($context));
        $key = $db->setQuery($query)->loadResult();
        return $key ? (string) $key : null;
    }

    private function getItemTable(string $context): string
    {
        $db = Factory::getDbo();
        return match ($context) {
            'com_menus.item' => $db->quoteName('#__menu'),
            default          => $db->quoteName('#__content'),
        };
    }
}
