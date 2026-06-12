<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class FinderExecutor
{
    public function finderSearch(array $params): array
    {
        $q = trim((string) ($params['query'] ?? ''));
        if ($q === '') {
            throw new \RuntimeException('Search query is required.');
        }

        $limit = max(1, min(50, (int) ($params['limit'] ?? 20)));
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $prefix = $db->getPrefix();

        if (!in_array($prefix . 'finder_links', $tables, true)) {
            return ['installed' => false, 'results' => [], 'message' => 'Smart Search (Finder) is not available.'];
        }

        $like = '%' . $db->escape($q, true) . '%';
        $query = $db->getQuery(true)
            ->select(['link_id', 'title', 'url', 'state', 'language', 'publish_start_date', 'publish_end_date'])
            ->from($db->quoteName('#__finder_links'))
            ->where('(' . $db->quoteName('title') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('description') . ' LIKE ' . $db->quote($like) . ')')
            ->where($db->quoteName('state') . ' = 1')
            ->order('title ASC');

        $results = $db->setQuery($query, 0, $limit)->loadAssocList() ?: [];

        return ['installed' => true, 'query' => $q, 'results' => $results, 'count' => count($results)];
    }
}
