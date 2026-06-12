<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Tag;

class TagExecutor
{
    public function createTag(array $params): array
    {
        $title = trim((string) ($params['title'] ?? ''));
        $table = new Tag(Factory::getDbo());

        if (!$table->save([
            'title'     => $title,
            'alias'     => OutputFilter::stringURLSafe($title),
            'published' => 1,
            'access'    => 1,
            'language'  => '*',
        ])) {
            throw new \RuntimeException('Failed to create tag: ' . $table->getError());
        }

        return ['id' => (int) $table->id, 'title' => $title, 'message' => 'Tag created.'];
    }

    public function updateTag(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $table = new Tag(Factory::getDbo());

        if (!$table->load($id)) {
            throw new \RuntimeException('Tag not found.');
        }

        foreach ($fields as $k => $v) {
            if (property_exists($table, $k)) {
                $table->$k = $v;
            }
        }

        if (!$table->store()) {
            throw new \RuntimeException('Failed to update tag.');
        }

        return ['id' => $id, 'message' => 'Tag updated.'];
    }

    public function deleteTag(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $table = new Tag(Factory::getDbo());

        if (!$table->load($id) || !$table->delete($id)) {
            throw new \RuntimeException('Failed to delete tag.');
        }

        return ['id' => $id, 'message' => 'Tag deleted.'];
    }
}
