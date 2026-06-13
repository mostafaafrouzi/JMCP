<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Sp;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Section presets sourced from existing SP demo pages on the site.
 */
class SpSectionLibrary
{
    private SpPageTree $tree;

    public function __construct(?SpPageTree $tree = null)
    {
        $this->tree = $tree ?? new SpPageTree();
    }

    /** @return array<int, array<string, mixed>> */
    public function listPresets(?int $limit = 30): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([
                'id', 'title', 'published',
                'CHAR_LENGTH(IFNULL(content, \'\')) AS content_length',
            ])
            ->from('#__sppagebuilder')
            ->order('id ASC');
        $pages = $db->setQuery($query, 0, max(1, min(100, (int) $limit)))->loadAssocList() ?: [];
        $presets = [];

        foreach ($pages as $page) {
            $content = json_decode((string) ($db->setQuery(
                $db->getQuery(true)->select('content')->from('#__sppagebuilder')->where('id = ' . (int) $page['id'])
            )->loadResult() ?? '[]'), true);

            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $ri => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $presets[] = [
                    'page_id'    => (int) $page['id'],
                    'page_title' => (string) ($page['title'] ?? ''),
                    'row_index'  => $ri,
                    'row_path'   => 'rows[' . $ri . ']',
                    'layout'     => (string) ($row['layout'] ?? ''),
                    'columns'    => count($row['columns'] ?? []),
                    'addon_count'=> $this->countAddonsInRow($row),
                ];
            }
        }

        return $presets;
    }

    /** @return array<string, mixed> */
    public function getRowPreset(int $pageId, int $rowIndex): array
    {
        $db = Factory::getDbo();
        $content = $db->setQuery(
            $db->getQuery(true)->select('content')->from('#__sppagebuilder')->where('id = ' . $pageId)
        )->loadResult();
        $rows = $this->tree->decode((string) $content);

        if (!isset($rows[$rowIndex]) || !is_array($rows[$rowIndex])) {
            throw new \RuntimeException('Row preset not found.');
        }

        return $rows[$rowIndex];
    }

    /** @param array<string, mixed> $row */
    private function countAddonsInRow(array $row): int
    {
        $count = 0;
        foreach ($row['columns'] ?? [] as $column) {
            if (!is_array($column)) {
                continue;
            }
            $count += count($column['addons'] ?? []);
        }

        return $count;
    }
}
