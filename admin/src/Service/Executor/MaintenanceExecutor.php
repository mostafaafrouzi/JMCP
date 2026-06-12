<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Site-wide maintenance: bulk text replace, content search, guided rebrand.
 */
class MaintenanceExecutor
{
    /** @var array<string, array<string, string[]>> */
    private const PRESETS = [
        'sp_pages' => [
            '#__sppagebuilder' => ['content', 'text', 'css', 'og_title', 'og_description'],
        ],
        'articles' => [
            '#__content' => ['title', 'introtext', 'fulltext', 'metadesc', 'metakey', 'alias'],
        ],
        'categories' => [
            '#__categories' => ['title', 'description', 'metadesc', 'metakey', 'alias'],
        ],
        'menus' => [
            '#__menu' => ['title', 'alias', 'params'],
        ],
        'modules' => [
            '#__modules' => ['title', 'content', 'params'],
        ],
        'template_styles' => [
            '#__template_styles' => ['title', 'params'],
        ],
        'contacts' => [
            '#__contact_details' => ['name', 'alias', 'con_position', 'address', 'suburb', 'state', 'country', 'postcode', 'telephone', 'mobile', 'fax', 'email', 'webpage', 'sortname1', 'sortname2', 'sortname3', 'metadesc', 'metakey'],
        ],
        'virtuemart_products' => [
            '#__virtuemart_products_en_gb' => ['product_name', 'product_s_desc', 'product_desc', 'slug', 'metadesc', 'metakey', 'customtitle'],
            '#__virtuemart_products_fa_ir' => ['product_name', 'product_s_desc', 'product_desc', 'slug', 'metadesc', 'metakey', 'customtitle'],
        ],
        'virtuemart_categories' => [
            '#__virtuemart_categories_en_gb' => ['category_name', 'category_description', 'slug', 'metadesc', 'metakey', 'customtitle'],
            '#__virtuemart_categories_fa_ir' => ['category_name', 'category_description', 'slug', 'metadesc', 'metakey', 'customtitle'],
        ],
        'virtuemart_vendors' => [
            '#__virtuemart_vendors_en_gb' => ['vendor_store_name', 'vendor_store_desc', 'vendor_phone', 'slug', 'customtitle'],
            '#__virtuemart_vendors_fa_ir' => ['vendor_store_name', 'vendor_store_desc', 'vendor_phone', 'slug', 'customtitle'],
        ],
        'tags' => [
            '#__tags' => ['title', 'alias', 'description', 'metadesc', 'metakey'],
        ],
    ];

    public function bulkContentReplace(array $params): array
    {
        $replacements = $this->normalizeReplacements($params['replacements'] ?? []);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $where = is_array($params['where'] ?? null) ? $params['where'] : [];
        $targets = $this->resolveTargets($params);
        $results = [];
        $totalAffected = 0;

        uksort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($targets as $table => $columns) {
            if (!$this->tableExists($table)) {
                $results[] = ['table' => $table, 'skipped' => true, 'reason' => 'table not found'];
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    $results[] = ['table' => $table, 'column' => $column, 'skipped' => true, 'reason' => 'column not found'];
                    continue;
                }

                $columnAffected = 0;
                foreach ($replacements as $from => $to) {
                    $count = $this->countMatches($table, $column, $from, $where);
                    if ($count === 0) {
                        continue;
                    }

                    if (!$dryRun) {
                        $this->runReplace($table, $column, $from, $to, $where);
                    }
                    $columnAffected += $count;
                }

                if ($columnAffected > 0) {
                    $results[] = [
                        'table'   => $table,
                        'column'  => $column,
                        'matches' => $columnAffected,
                        'dry_run' => $dryRun,
                    ];
                    $totalAffected += $columnAffected;
                }
            }
        }

        return [
            'dry_run'        => $dryRun,
            'total_matches'  => $totalAffected,
            'replacements'   => count($replacements),
            'results'        => $results,
            'message'        => $dryRun ? 'Dry run complete.' : 'Bulk replace applied.',
        ];
    }

    public function searchSiteContent(array $params): array
    {
        $needle = (string) ($params['needle'] ?? '');
        if ($needle === '') {
            throw new \RuntimeException('needle is required.');
        }

        $limit = max(1, min(50, (int) ($params['limit_per_column'] ?? 5)));
        if (empty($params['preset']) && empty($params['presets']) && empty($params['targets'])) {
            $params['presets'] = array_keys(self::PRESETS);
        }
        $targets = $this->resolveTargets($params);
        $hits = [];

        foreach ($targets as $table => $columns) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    continue;
                }

                $rows = $this->findNeedleRows($table, $column, $needle, $limit);
                if ($rows !== []) {
                    $hits[] = [
                        'table'  => $table,
                        'column' => $column,
                        'count'  => count($rows),
                        'samples'=> $rows,
                    ];
                }
            }
        }

        return ['needle' => $needle, 'hits' => $hits];
    }

    public function siteRebrand(array $params): array
    {
        $brand = trim((string) ($params['brand'] ?? ''));
        if ($brand === '') {
            throw new \RuntimeException('brand is required.');
        }

        $oldBrand = trim((string) ($params['old_brand'] ?? ''));
        $metaDesc = (string) ($params['meta_desc'] ?? '');
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $clearCache = (bool) ($params['clear_cache'] ?? true);
        $cloneVmLang = (bool) ($params['clone_vm_language_tables'] ?? true);

        $steps = [];

        $configFields = ['sitename' => $brand, 'fromname' => $brand];
        if ($metaDesc !== '') {
            $configFields['MetaDesc'] = $metaDesc;
        }
        $site = new SiteExecutor();
        $steps['global_config'] = $site->updateGlobalConfig([
            'fields'  => $configFields,
            'dry_run' => $dryRun,
        ]);

        $replacements = $this->buildRebrandReplacements($brand, $oldBrand);
        $presets = (array) ($params['presets'] ?? [
            'sp_pages', 'articles', 'categories', 'menus', 'modules',
            'template_styles', 'contacts', 'virtuemart_products',
            'virtuemart_categories', 'virtuemart_vendors', 'tags',
        ]);

        foreach ($presets as $preset) {
            $steps['bulk_' . $preset] = $this->bulkContentReplace([
                'preset'       => (string) $preset,
                'replacements' => $replacements,
                'dry_run'      => $dryRun,
            ]);
        }

        if ($cloneVmLang && !$dryRun) {
            $shop = new ShopExecutor();
            $steps['virtuemart_clone_language_tables'] = $shop->virtuemartCloneLanguageTables([
                'source_suffix' => (string) ($params['vm_source_lang'] ?? 'en_gb'),
                'target_suffix' => (string) ($params['vm_target_lang'] ?? 'fa_ir'),
            ]);
        }

        if ($clearCache && !$dryRun) {
            $system = new SystemExecutor();
            $steps['cache'] = $system->runCacheClean([]);
        }

        return [
            'brand'   => $brand,
            'dry_run' => $dryRun,
            'steps'   => $steps,
            'message' => $dryRun ? 'Rebrand dry run complete.' : 'Site rebrand applied via MCP.',
        ];
    }

    /** @return array<string, string> */
    private function buildRebrandReplacements(string $brand, string $oldBrand): array
    {
        $map = [
            'UT Resto' => $brand,
            'VirtueMart 3 Sample store' => $brand,
            'Pizza' => 'گیربکس',
            'pizza' => 'گیربکس',
            'Restaurant' => 'اتو سرویس',
            'Fast Food' => 'تعویض روغن',
            'Chef' => 'کارشناس',
            'Chefs' => 'کارشناسان',
            'Burger' => 'روغن ATF',
            'Burritos' => 'روغن موتور',
            'Chicken' => 'فیلتر گیربکس',
            'Tacos' => 'لوازم یدکی',
            'Desserts' => 'پکیج سرویس',
            'Contact Us' => 'تماس با ما',
            'About Us' => 'درباره ما',
            'Unitemplates' => $brand,
        ];

        if ($oldBrand !== '') {
            $map[$oldBrand] = $brand;
        }

        return $map;
    }

    /** @return array<string, string[]> */
    private function resolveTargets(array $params): array
    {
        if (!empty($params['preset'])) {
            $preset = (string) $params['preset'];
            if (!isset(self::PRESETS[$preset])) {
                throw new \RuntimeException('Unknown preset: ' . $preset);
            }

            return $this->filterExistingTargets(self::PRESETS[$preset]);
        }

        if (!empty($params['presets']) && is_array($params['presets'])) {
            $merged = [];
            foreach ($params['presets'] as $preset) {
                $preset = (string) $preset;
                if (!isset(self::PRESETS[$preset])) {
                    continue;
                }
                foreach (self::PRESETS[$preset] as $table => $columns) {
                    $merged[$table] = array_values(array_unique(array_merge($merged[$table] ?? [], $columns)));
                }
            }

            return $this->filterExistingTargets($merged);
        }

        $targets = $params['targets'] ?? null;
        if (!is_array($targets) || $targets === []) {
            throw new \RuntimeException('Provide preset, presets, or targets.');
        }

        $normalized = [];
        foreach ($targets as $table => $columns) {
            $table = $this->normaliseTable((string) $table);
            $normalized[$table] = array_values(array_unique(array_map('strval', (array) $columns)));
        }

        return $this->filterExistingTargets($normalized);
    }

    /** @param array<string, string[]> $targets @return array<string, string[]> */
    private function filterExistingTargets(array $targets): array
    {
        $out = [];
        foreach ($targets as $table => $columns) {
            if ($this->tableExists($table)) {
                $out[$table] = $columns;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private function normalizeReplacements(mixed $replacements): array
    {
        if (!is_array($replacements) || $replacements === []) {
            throw new \RuntimeException('replacements array is required (from/to pairs).');
        }

        $normalized = [];
        foreach ($replacements as $key => $item) {
            if (is_array($item)) {
                $from = (string) ($item['from'] ?? '');
                if ($from === '') {
                    continue;
                }
                $normalized[$from] = (string) ($item['to'] ?? '');
                continue;
            }
            $from = (string) $key;
            if ($from === '') {
                continue;
            }
            $normalized[$from] = (string) $item;
        }

        if ($normalized === []) {
            throw new \RuntimeException('At least one valid from/to replacement is required.');
        }

        return $normalized;
    }

    /** @param array<string, scalar> $where */
    private function countMatches(string $table, string $column, string $from, array $where = []): int
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table))
            ->where($db->quoteName($column) . ' LIKE ' . $db->quote('%' . $this->escapeLike($from) . '%', false));

        $this->applyWhere($query, $table, $where);

        return (int) $db->setQuery($query)->loadResult();
    }

    /** @param array<string, scalar> $where */
    private function runReplace(string $table, string $column, string $from, string $to, array $where = []): void
    {
        $db = Factory::getDbo();
        $quotedTable = $db->quoteName($table);
        $quotedColumn = $db->quoteName($column);
        $sql = 'UPDATE ' . $quotedTable
            . ' SET ' . $quotedColumn . ' = REPLACE(' . $quotedColumn . ', '
            . $db->quote($from) . ', ' . $db->quote($to) . ')'
            . ' WHERE ' . $quotedColumn . ' LIKE ' . $db->quote('%' . $this->escapeLike($from) . '%', false);

        foreach ($where as $field => $value) {
            $field = (string) $field;
            if (!$this->columnExists($table, $field)) {
                continue;
            }
            $sql .= ' AND ' . $db->quoteName($field) . ' = ' . $db->quote((string) $value);
        }

        $db->setQuery($sql)->execute();
    }

    /** @param array<string, scalar> $where */
    private function applyWhere($query, string $table, array $where): void
    {
        $db = Factory::getDbo();
        foreach ($where as $field => $value) {
            $field = (string) $field;
            if ($this->columnExists($table, $field)) {
                $query->where($db->quoteName($field) . ' = ' . $db->quote((string) $value));
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function findNeedleRows(string $table, string $column, string $needle, int $limit): array
    {
        $db = Factory::getDbo();
        $pk = $this->guessPrimaryKey($table);
        $select = $pk !== null
            ? [$db->quoteName($pk) . ' AS id', 'LEFT(' . $db->quoteName($column) . ', 200) AS snippet']
            : ['LEFT(' . $db->quoteName($column) . ', 200) AS snippet'];

        $query = $db->getQuery(true)
            ->select($select)
            ->from($db->quoteName($table))
            ->where($db->quoteName($column) . ' LIKE ' . $db->quote('%' . $this->escapeLike($needle) . '%', false));

        if ($pk !== null) {
            $query->order($db->quoteName($pk) . ' ASC');
        }

        return $db->setQuery($query, 0, $limit)->loadAssocList() ?: [];
    }

    private function guessPrimaryKey(string $table): ?string
    {
        $candidates = ['id', 'virtuemart_product_id', 'virtuemart_category_id', 'virtuemart_vendor_id'];
        foreach ($candidates as $col) {
            if ($this->columnExists($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function normaliseTable(string $table): string
    {
        $db = Factory::getDbo();
        $prefix = $db->getPrefix();

        if (str_starts_with($table, $prefix)) {
            return $table;
        }

        if (str_starts_with($table, '#__')) {
            return $db->replacePrefix($table);
        }

        return $prefix . $table;
    }

    private function tableExists(string $table): bool
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];

        return in_array($this->normaliseTable($table), $tables, true);
    }

    private function columnExists(string $table, string $column): bool
    {
        $db = Factory::getDbo();
        $columns = $db->getTableColumns($this->normaliseTable($table), false);

        return isset($columns[$column]);
    }
}
