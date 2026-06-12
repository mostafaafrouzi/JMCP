<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\PathGuard;

class SnapshotExecutor
{
    private const SNAPSHOT_TABLES = [
        '#__content', '#__categories', '#__menu', '#__modules', '#__template_styles',
        '#__sppagebuilder', '#__virtuemart_products', '#__virtuemart_categories',
        '#__virtuemart_product_prices', '#__extensions',
    ];

    private PathGuard $guard;

    public function __construct(?PathGuard $guard = null)
    {
        $this->guard = $guard ?? new PathGuard();
    }

    public function createSiteSnapshot(array $params): array
    {
        $label = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['label'] ?? 'snapshot')) ?: 'snapshot';
        $tables = (array) ($params['tables'] ?? self::SNAPSHOT_TABLES);
        $db = Factory::getDbo();
        $data = [
            'created'  => Factory::getDate('now', 'UTC')->toSql(true),
            'joomla'   => JVERSION,
            'sitename' => (string) Factory::getApplication()->getConfig()->get('sitename'),
            'tables'   => [],
        ];

        foreach ($tables as $table) {
            $table = (string) $table;
            if (!str_starts_with($table, '#__')) {
                continue;
            }
            try {
                $rows = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName($table)))->loadAssocList() ?: [];
                $data['tables'][$table] = $rows;
            } catch (\Throwable $e) {
                $data['tables'][$table] = ['_error' => $e->getMessage()];
            }
        }

        $dir = JPATH_ROOT . '/tmp/jmcp_snapshots';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create snapshot directory.');
        }

        $filename = $label . '_' . date('Ymd_His') . '.json';
        $path = $dir . '/' . $filename;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new \RuntimeException('Failed to write snapshot file.');
        }

        return [
            'path'         => 'tmp/jmcp_snapshots/' . $filename,
            'tables_count' => count($data['tables']),
            'size_bytes'   => filesize($path),
            'message'      => 'Site snapshot created.',
        ];
    }

    public function restoreSiteSnapshot(array $params): array
    {
        $path = trim((string) ($params['path'] ?? ''));
        $tables = (array) ($params['tables'] ?? []);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($path === '') {
            throw new \RuntimeException('path to snapshot JSON is required.');
        }

        $absolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
            ? $path
            : $this->guard->resolve($path);

        $json = file_get_contents($absolute);
        if ($json === false) {
            throw new \RuntimeException('Snapshot file not readable.');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['tables']) || !is_array($data['tables'])) {
            throw new \RuntimeException('Invalid snapshot format.');
        }

        $db = Factory::getDbo();
        $restored = [];

        foreach ($data['tables'] as $table => $rows) {
            if ($tables !== [] && !in_array($table, $tables, true)) {
                continue;
            }
            if (!is_array($rows) || isset($rows['_error'])) {
                continue;
            }
            if ($dryRun) {
                $restored[] = ['table' => $table, 'rows' => count($rows), 'dry_run' => true];
                continue;
            }

            $db->setQuery('TRUNCATE ' . $db->quoteName($table))->execute();
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $db->insertObject($table, (object) $row);
            }
            $restored[] = ['table' => $table, 'rows' => count($rows)];
        }

        return [
            'dry_run'  => $dryRun,
            'restored' => $restored,
            'message'  => $dryRun ? 'Dry run: snapshot restore validated.' : 'Snapshot restored.',
        ];
    }
}
