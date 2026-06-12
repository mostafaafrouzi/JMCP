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

class DatabaseExecutor
{
    public function listDbTables(array $params): array
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];

        return [
            'prefix' => $db->getPrefix(),
            'tables' => array_values($tables),
        ];
    }

    public function getDbTableColumns(array $params): array
    {
        $table = $this->normaliseTable((string) ($params['table'] ?? ''));
        $db = Factory::getDbo();

        $columns = $db->getTableColumns($table, false);

        if (empty($columns)) {
            throw new \RuntimeException(sprintf('Table %s not found or has no columns.', $table));
        }

        return [
            'table'   => $table,
            'columns' => $columns,
        ];
    }

    public function executeSql(array $params): array
    {
        $sql = trim((string) ($params['sql'] ?? ''));

        if ($sql === '') {
            throw new \RuntimeException('SQL query cannot be empty.');
        }

        $db = Factory::getDbo();
        $db->setQuery($sql);

        $firstWord = strtoupper(strtok($sql, " \t\n\r"));

        if ($firstWord === 'SELECT' || $firstWord === 'SHOW' || $firstWord === 'DESCRIBE' || $firstWord === 'EXPLAIN') {
            return [
                'type'    => 'select',
                'rows'    => $db->loadAssocList() ?: [],
                'count'   => $db->getNumRows(),
            ];
        }

        $db->execute();

        return [
            'type'          => 'execute',
            'affected_rows' => $db->getAffectedRows(),
            'message'       => 'Query executed successfully.',
        ];
    }

    private function normaliseTable(string $table): string
    {
        $db = Factory::getDbo();
        $prefix = $db->getPrefix();

        if (str_starts_with($table, '#__')) {
            return $prefix . substr($table, 3);
        }

        return $table;
    }
}
