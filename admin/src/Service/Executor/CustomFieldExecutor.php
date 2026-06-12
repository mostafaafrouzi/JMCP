<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class CustomFieldExecutor
{
    public function listCustomFields(array $params): array
    {
        $context = (string) ($params['context'] ?? 'com_content.article');
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'name', 'type', 'group_id', 'label', 'required', 'state', 'language'])
            ->from('#__fields')
            ->where('context = ' . $db->quote($context))
            ->order('ordering ASC');

        return ['fields' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getCustomField(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('*')->from('#__fields')->where('id = ' . $id);
        $field = $db->setQuery($query)->loadAssoc();
        if (!$field) {
            throw new \RuntimeException('Custom field not found.');
        }
        return $field;
    }

    public function createCustomField(array $params): array
    {
        $data = (object) [
            'title'    => (string) ($params['title'] ?? ''),
            'name'     => (string) ($params['name'] ?? ''),
            'type'     => (string) ($params['type'] ?? 'text'),
            'context'  => (string) ($params['context'] ?? 'com_content.article'),
            'group_id' => (int) ($params['group_id'] ?? 0),
            'label'    => (string) ($params['label'] ?? $params['title'] ?? ''),
            'state'    => 1,
            'language' => '*',
            'params'   => '{}',
        ];

        $db = Factory::getDbo();
        $db->insertObject('#__fields', $data);

        return ['id' => (int) $db->insertid(), 'message' => 'Custom field created.'];
    }

    public function updateFieldValues(array $params): array
    {
        $itemId = (int) ($params['item_id'] ?? 0);
        $values = (array) ($params['values'] ?? []);
        $db = Factory::getDbo();

        foreach ($values as $fieldId => $value) {
            $query = $db->getQuery(true)
                ->select('field_id')
                ->from('#__fields_values')
                ->where('item_id = ' . $itemId)
                ->where('field_id = ' . (int) $fieldId);
            $exists = $db->setQuery($query)->loadResult();

            if ($exists) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update('#__fields_values')
                        ->set('value = ' . $db->quote((string) $value))
                        ->where('item_id = ' . $itemId)
                        ->where('field_id = ' . (int) $fieldId)
                )->execute();
            } else {
                $row = new \stdClass();
                $row->field_id = (int) $fieldId;
                $row->item_id  = $itemId;
                $row->value    = (string) $value;
                $db->insertObject('#__fields_values', $row);
            }
        }

        return ['item_id' => $itemId, 'message' => 'Field values updated.'];
    }

    public function updateCustomField(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);

        if ($id <= 0 || $fields === []) {
            throw new \RuntimeException('id and fields are required.');
        }

        if (isset($fields['params']) && is_array($fields['params'])) {
            $fields['params'] = json_encode($fields['params']);
        }

        $fields['id'] = $id;
        Factory::getDbo()->updateObject('#__fields', (object) $fields, 'id');

        return ['id' => $id, 'message' => 'Custom field updated.'];
    }

    public function deleteCustomField(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('id is required.');
        }

        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)->delete('#__fields')->where('id = ' . $id))->execute();
        $db->setQuery($db->getQuery(true)->delete('#__fields_values')->where('field_id = ' . $id))->execute();

        return ['id' => $id, 'message' => 'Custom field deleted.'];
    }
}
