<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Sp;

defined('_JEXEC') or die;

/**
 * Parse and mutate SP Page Builder content JSON trees.
 */
class SpPageTree
{
    /** @return array<int, mixed> */
    public function decode(string $content): array
    {
        if ($content === '' || $content === '[]') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('SP page content is not valid JSON.');
        }

        return $decoded;
    }

    /** @param array<int, mixed> $rows */
    public function encode(array $rows): string
    {
        return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /** @param array<int, mixed> $rows @return array<int, array<string, mixed>> */
    public function buildTree(array $rows, bool $fieldPreview = true): array
    {
        $nodes = [];

        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowPath = 'rows[' . $ri . ']';
            $nodes[] = [
                'path'    => $rowPath,
                'type'    => 'row',
                'layout'  => (string) ($row['layout'] ?? ''),
                'columns' => count($row['columns'] ?? []),
            ];

            foreach ($row['columns'] ?? [] as $ci => $column) {
                if (!is_array($column)) {
                    continue;
                }
                $colPath = $rowPath . '.columns[' . $ci . ']';
                $nodes[] = [
                    'path'   => $colPath,
                    'type'   => 'column',
                    'addons' => count($column['addons'] ?? []),
                ];

                foreach ($column['addons'] ?? [] as $ai => $addon) {
                    if (!is_array($addon)) {
                        continue;
                    }
                    $addonPath = $colPath . '.addons[' . $ai . ']';
                    $node = [
                        'path' => $addonPath,
                        'type' => 'addon',
                        'name' => (string) ($addon['name'] ?? ''),
                        'id'   => $addon['id'] ?? null,
                    ];
                    if ($fieldPreview && isset($addon['settings']) && is_array($addon['settings'])) {
                        $node['settings_preview'] = $this->previewSettings($addon['settings']);
                    }
                    $nodes[] = $node;
                }
            }
        }

        return $nodes;
    }

    /** @param array<int, mixed> $rows */
    public function getNode(array $rows, string $path): mixed
    {
        return $this->resolve($rows, $path, false);
    }

    /** @param array<int, mixed> $rows */
    public function setAddonField(array &$rows, string $path, string $field, mixed $value): void
    {
        $node = $this->resolve($rows, $path, true);
        if (!is_array($node) || ($node['type'] ?? '') !== 'addon' && !isset($node['name'])) {
            throw new \RuntimeException('Path must point to an addon node.');
        }

        if (!isset($node['settings']) || !is_array($node['settings'])) {
            $node['settings'] = [];
        }

        $this->setByDot($node['settings'], $field, $value);
        $this->writeBack($rows, $path, $node);
    }

    /** @param array<int, mixed> $rows */
    public function setRowField(array &$rows, string $path, string $field, mixed $value): void
    {
        $node = $this->resolve($rows, $path, true);
        if (!is_array($node)) {
            throw new \RuntimeException('Invalid row path.');
        }

        if ($field === 'layout') {
            $node['layout'] = (string) $value;
        } elseif (str_starts_with($field, 'settings.')) {
            if (!isset($node['settings']) || !is_array($node['settings'])) {
                $node['settings'] = [];
            }
            $this->setByDot($node['settings'], substr($field, 9), $value);
        } else {
            throw new \RuntimeException('Unsupported row field: ' . $field);
        }

        $this->writeBack($rows, $path, $node);
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, mixed> $settings
     */
    public function addRow(array &$rows, string $layout = '12', ?int $afterIndex = null, array $settings = []): string
    {
        $rowId = $this->newId();
        $rowSettings = array_replace_recursive($this->defaultRowSettings($rowId), $settings);
        $rowSettings['instFormId'] = $rowId;

        $row = [
            'id'         => $rowId,
            'visibility' => true,
            'collapse'   => false,
            'settings'   => $rowSettings,
            'layout'     => $layout,
            'columns'    => $this->buildColumnsForLayout($layout),
        ];

        if ($afterIndex === null || $afterIndex < 0 || $afterIndex >= count($rows)) {
            $rows[] = $row;
            $index = count($rows) - 1;
        } else {
            array_splice($rows, $afterIndex + 1, 0, [$row]);
            $index = $afterIndex + 1;
        }

        return 'rows[' . $index . ']';
    }

    /** @param array<int, mixed> $rows */
    public function addAddon(
        array &$rows,
        string $columnPath,
        string $addonName,
        string $addonType,
        array $settings,
        ?string $title = null,
        ?string $icon = null
    ): string {
        $column = $this->resolve($rows, $columnPath, true);
        if (!is_array($column)) {
            throw new \RuntimeException('Column path not found: ' . $columnPath);
        }
        if (!isset($column['addons']) || !is_array($column['addons'])) {
            $column['addons'] = [];
        }

        $addon = [
            'id'         => $this->newId(),
            'type'       => $addonType,
            'name'       => $addonName,
            'visibility' => true,
            'settings'   => $settings,
            'title'      => $title ?? ucfirst($addonName),
            'parent'     => false,
        ];
        if ($icon !== null) {
            $addon['icon'] = $icon;
        }

        $column['addons'][] = $addon;
        $this->writeBack($rows, $columnPath, $column);
        $ai = count($column['addons']) - 1;

        return $columnPath . '.addons[' . $ai . ']';
    }

    /** @param array<int, mixed> $rows */
    public function deleteNode(array &$rows, string $path): void
    {
        if (!preg_match('/^rows\[(\d+)\](?:\.columns\[(\d+)\](?:\.addons\[(\d+)\])?)?$/', $path, $m)) {
            throw new \RuntimeException('Invalid path for delete: ' . $path);
        }

        $ri = (int) $m[1];

        if (!isset($rows[$ri])) {
            throw new \RuntimeException('Node not found: ' . $path);
        }

        if (!isset($m[2]) || $m[2] === '') {
            array_splice($rows, $ri, 1);
            return;
        }

        $ci = (int) $m[2];
        $columns = &$rows[$ri]['columns'];
        if (!is_array($columns) || !isset($columns[$ci])) {
            throw new \RuntimeException('Column not found: ' . $path);
        }

        if (!isset($m[3]) || $m[3] === '') {
            array_splice($columns, $ci, 1);
            return;
        }

        $ai = (int) $m[3];
        $addons = &$columns[$ci]['addons'];
        if (!is_array($addons) || !isset($addons[$ai])) {
            throw new \RuntimeException('Addon not found: ' . $path);
        }
        array_splice($addons, $ai, 1);
    }

    /** @param array<int, mixed> $rows */
    public function cloneRow(array &$rows, string $rowPath, ?int $afterIndex = null): string
    {
        $row = $this->resolve($rows, $rowPath, false);
        if (!is_array($row)) {
            throw new \RuntimeException('Row not found: ' . $rowPath);
        }

        $copy = json_decode(json_encode($row, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $copy = $this->regenerateIds($copy);

        if (!preg_match('/^rows\[(\d+)\]$/', $rowPath, $m)) {
            throw new \RuntimeException('cloneRow requires a row path.');
        }

        $sourceIndex = (int) $m[1];
        $insertAt = $afterIndex ?? $sourceIndex;

        if ($insertAt < 0 || $insertAt >= count($rows)) {
            $rows[] = $copy;
            return 'rows[' . (count($rows) - 1) . ']';
        }

        array_splice($rows, $insertAt + 1, 0, [$copy]);

        return 'rows[' . ($insertAt + 1) . ']';
    }

    /** @param array<int, mixed> $rows */
    public function insertRowFromData(array &$rows, array $rowData, ?int $afterIndex = null): string
    {
        $copy = json_decode(json_encode($rowData, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $copy = $this->regenerateIds($copy);

        if ($afterIndex === null || $afterIndex < 0 || $afterIndex >= count($rows)) {
            $rows[] = $copy;
            return 'rows[' . (count($rows) - 1) . ']';
        }

        array_splice($rows, $afterIndex + 1, 0, [$copy]);

        return 'rows[' . ($afterIndex + 1) . ']';
    }

    /** @param array<int, mixed> $rows */
    public function setColumnField(array &$rows, string $path, string $field, mixed $value): void
    {
        $node = $this->resolve($rows, $path, true);
        if (!is_array($node)) {
            throw new \RuntimeException('Invalid column path.');
        }
        if (!isset($node['settings']) || !is_array($node['settings'])) {
            $node['settings'] = [];
        }
        $this->setByDot($node['settings'], $field, $value);
        $this->writeBack($rows, $path, $node);
    }

    /** @param array<int, mixed> $rows */
    public function cloneAddon(array &$rows, string $addonPath, ?int $insertAfter = null): string
    {
        if (!preg_match('/^(rows\[\d+\]\.columns\[\d+\])\.addons\[(\d+)\]$/', $addonPath, $m)) {
            throw new \RuntimeException('cloneAddon requires an addon path.');
        }

        $columnPath = $m[1];
        $sourceIndex = (int) $m[2];
        $column = $this->resolve($rows, $columnPath, true);
        if (!is_array($column) || !isset($column['addons'][$sourceIndex]) || !is_array($column['addons'][$sourceIndex])) {
            throw new \RuntimeException('Addon not found for clone.');
        }

        $copy = json_decode(json_encode($column['addons'][$sourceIndex], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $copy['id'] = $this->newId();
        if (isset($copy['settings']['instFormId'])) {
            $copy['settings']['instFormId'] = $copy['id'];
        }

        $insertAt = $insertAfter ?? $sourceIndex;
        array_splice($column['addons'], $insertAt + 1, 0, [$copy]);
        $this->writeBack($rows, $columnPath, $column);

        return $columnPath . '.addons[' . ($insertAt + 1) . ']';
    }

    /** @param array<int, mixed> $rows @param array<string, mixed> $itemFields */
    public function addRepeatableItem(array &$rows, string $addonPath, string $repeatableKey, array $itemFields): string
    {
        $addon = $this->resolve($rows, $addonPath, true);
        if (!is_array($addon) || !isset($addon['name'])) {
            throw new \RuntimeException('Path must point to an addon.');
        }
        if (!isset($addon['settings']) || !is_array($addon['settings'])) {
            $addon['settings'] = [];
        }
        if (!isset($addon['settings'][$repeatableKey]) || !is_array($addon['settings'][$repeatableKey])) {
            $addon['settings'][$repeatableKey] = [];
        }

        $item = array_replace_recursive($itemFields, ['id' => $this->newUniqueStringId()]);
        $addon['settings'][$repeatableKey][] = $item;
        $this->writeBack($rows, $addonPath, $addon);
        $index = count($addon['settings'][$repeatableKey]) - 1;

        return $addonPath . '.settings.' . $repeatableKey . '[' . $index . ']';
    }

    /** @param array<int, mixed> $rows @param array<string, mixed> $fields */
    public function setAddonFields(array &$rows, string $addonPath, array $fields): void
    {
        foreach ($fields as $field => $value) {
            $this->setAddonField($rows, $addonPath, (string) $field, $value);
        }
    }

    /** @param array<int, mixed> $rows */
    public function moveNode(array &$rows, string $fromPath, string $toParentPath, ?int $toIndex = null): string
    {
        $node = $this->resolve($rows, $fromPath, false);
        if (!is_array($node)) {
            throw new \RuntimeException('Source node not found.');
        }

        $this->deleteNode($rows, $fromPath);
        $node = json_decode(json_encode($node, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        if (preg_match('/^rows\[\d+\]\.columns\[\d+\]$/', $toParentPath)) {
            $column = $this->resolve($rows, $toParentPath, true);
            if (!is_array($column)) {
                throw new \RuntimeException('Target column not found.');
            }
            if (!isset($column['addons']) || !is_array($column['addons'])) {
                $column['addons'] = [];
            }
            if ($toIndex === null || $toIndex < 0 || $toIndex > count($column['addons'])) {
                $column['addons'][] = $node;
                $index = count($column['addons']) - 1;
            } else {
                array_splice($column['addons'], $toIndex, 0, [$node]);
                $index = $toIndex;
            }
            $this->writeBack($rows, $toParentPath, $column);

            return $toParentPath . '.addons[' . $index . ']';
        }

        throw new \RuntimeException('moveNode currently supports moving addons into columns.');
    }

    /** @param array<int, mixed> $rows @return array<int, array<string, mixed>> */
    public function findAddonPaths(array $rows, ?string $addonName = null): array
    {
        $matches = [];
        $needle = $addonName !== null ? strtolower($addonName) : null;

        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row['columns'] ?? [] as $ci => $column) {
                if (!is_array($column)) {
                    continue;
                }
                foreach ($column['addons'] ?? [] as $ai => $addon) {
                    if (!is_array($addon)) {
                        continue;
                    }
                    $name = (string) ($addon['name'] ?? '');
                    if ($needle !== null && strtolower($name) !== $needle) {
                        continue;
                    }
                    $path = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . ']';
                    $matches[] = [
                        'path' => $path,
                        'name' => $name,
                        'id'   => $addon['id'] ?? null,
                    ];
                }
            }
        }

        return $matches;
    }

    /** @param array<int, mixed> $rows @return array<int, string> */
    public function validateStructure(array $rows): array
    {
        $errors = [];

        foreach ($rows as $ri => $row) {
            if (!is_array($row)) {
                $errors[] = 'rows[' . $ri . '] is not an object';
                continue;
            }
            if (!isset($row['id'])) {
                $errors[] = 'rows[' . $ri . '] missing id';
            }
            if (!isset($row['columns']) || !is_array($row['columns'])) {
                $errors[] = 'rows[' . $ri . '] missing columns array';
                continue;
            }
            foreach ($row['columns'] as $ci => $column) {
                if (!is_array($column)) {
                    $errors[] = 'rows[' . $ri . '].columns[' . $ci . '] invalid';
                    continue;
                }
                foreach ($column['addons'] ?? [] as $ai => $addon) {
                    if (!is_array($addon)) {
                        $errors[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . '] invalid';
                        continue;
                    }
                    if (empty($addon['name'])) {
                        $errors[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . '] missing name';
                    }
                    if (!isset($addon['settings']) || !is_array($addon['settings'])) {
                        $errors[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . '] missing settings';
                    }
                }
            }
        }

        return $errors;
    }

    public function newId(): int
    {
        return (int) round(microtime(true) * 1000) + random_int(1, 999);
    }

    public function newUniqueStringId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** @return array<int, array<string, mixed>> */
    private function buildColumnsForLayout(string $layout): array
    {
        $parts = array_map('trim', explode('+', $layout));
        $columns = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $columns[] = [
                'id'         => $this->newId(),
                'class_name' => 'row-column',
                'visibility' => true,
                'settings'   => $this->defaultColumnSettings($part),
                'addons'     => [],
            ];
        }

        if ($columns === []) {
            $columns[] = [
                'id'         => $this->newId(),
                'class_name' => 'row-column',
                'visibility' => true,
                'settings'   => $this->defaultColumnSettings('12'),
                'addons'     => [],
            ];
        }

        return $columns;
    }

    /** @return array<string, string> */
    private function columnWidthFromLayoutPart(string $part): array
    {
        $units = (float) $part;
        if ($units <= 0.0) {
            $units = 12.0;
        }
        $pct = rtrim(rtrim(number_format(($units / 12.0) * 100, 4, '.', ''), '0'), '.') . '%';

        return [
            'xl' => $pct,
            'lg' => $pct,
            'md' => $pct,
            'sm' => '100%',
            'xs' => '100%',
        ];
    }

    /** @return array<string, mixed> */
    private function defaultRowSettings(?int $rowId = null): array
    {
        return [
            'admin_label'               => '',
            'fit_columns'               => ['xl' => true, 'sm' => false],
            'background_type'           => 'none',
            'background_gradient'       => ['color' => '#00c6fb', 'color2' => '#005bea', 'deg' => '45', 'type' => 'linear'],
            'background_image'          => ['src' => ''],
            'background_repeat'         => 'no-repeat',
            'background_size'           => 'cover',
            'background_attachment'     => 'inherit',
            'background_position'       => '50% 50%',
            'overlay_type'              => 'overlay_none',
            'gradient_overlay'          => ['color' => 'rgba(127, 0, 255, 0.8)', 'color2' => 'rgba(225, 0, 255, 0.7)', 'deg' => '45', 'type' => 'linear'],
            'pattern_overlay'           => '',
            'overlay_pattern_color'     => '',
            'blend_mode'                => 'normal',
            'columns_align_center'      => 0,
            'columns_content_alignment' => 'center',
            'stretch_section'           => 0,
            'fullscreen'                => 0,
            'no_gutter'                 => 0,
            'padding'                   => ['xl' => '0px 0px 0px 0px', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''],
            'margin'                    => ['xl' => '0px 0px 0px 0px', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''],
            'enable_animation'          => '1',
            'animationduration'         => '300',
            'animationdelay'            => '0',
            'instFormId'                => $rowId ?? 0,
            'hidden_xl'                 => '',
            'hidden_lg'                 => '',
            'hidden_md'                 => '',
            'hidden_sm'                 => '',
            'hidden_xs'                 => '',
        ];
    }

    /** @return array<string, mixed> */
    private function defaultColumnSettings(string $layoutPart = '12'): array
    {
        return [
            'background_type'           => 'none',
            'background_gradient'       => ['color' => '#00c6fb', 'color2' => '#005bea', 'deg' => '45', 'type' => 'linear'],
            'background_image'          => ['src' => ''],
            'background_repeat'         => 'no-repeat',
            'background_size'           => 'cover',
            'background_attachment'     => 'scroll',
            'background_position'       => '0 0',
            'overlay_type'              => 'overlay_none',
            'gradient_overlay'          => ['color' => 'rgba(127, 0, 255, 0.8)', 'color2' => 'rgba(225, 0, 255, 0.7)', 'deg' => '45', 'type' => 'linear'],
            'pattern_overlay'           => ['src' => ''],
            'overlay_pattern_color'     => '',
            'blend_mode'                => 'normal',
            'use_border'                => 0,
            'items_align_center'        => 0,
            'items_content_alignment'   => 'center',
            'enable_animation'          => '1',
            'animationduration'         => '300',
            'animationdelay'            => '0',
            'padding'                   => ['xl' => '', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''],
            'margin'                    => ['xl' => '', 'lg' => '', 'md' => '', 'sm' => '', 'xs' => ''],
            'width'                     => $this->columnWidthFromLayoutPart($layoutPart),
            'hidden_xl'                 => '',
            'hidden_lg'                 => '',
            'hidden_md'                 => '',
            'hidden_sm'                 => '',
            'hidden_xs'                 => '',
        ];
    }

    /**
     * Repair column widths and row settings on an existing page tree.
     *
     * @param array<int, mixed> $rows
     */
    public function repairLayout(array &$rows): array
    {
        $fixed = ['rows' => 0, 'columns' => 0];

        foreach (array_keys($rows) as $ri) {
            if (!is_array($rows[$ri])) {
                continue;
            }

            $rowId = (int) ($rows[$ri]['id'] ?? $this->newId());
            if (!isset($rows[$ri]['id'])) {
                $rows[$ri]['id'] = $rowId;
            }
            $rows[$ri]['settings'] = array_replace_recursive(
                $this->defaultRowSettings($rowId),
                is_array($rows[$ri]['settings'] ?? null) ? $rows[$ri]['settings'] : []
            );
            $rows[$ri]['settings']['instFormId'] = $rowId;
            $fixed['rows']++;

            $layout = (string) ($rows[$ri]['layout'] ?? '12');
            $parts = array_map('trim', explode('+', $layout));
            if ($parts === [] || $parts === ['']) {
                $parts = ['12'];
            }

            $columns = $rows[$ri]['columns'] ?? [];
            if (!is_array($columns)) {
                continue;
            }

            foreach (array_keys($columns) as $ci) {
                if (!is_array($columns[$ci])) {
                    continue;
                }
                $part = $parts[$ci] ?? $parts[0] ?? '12';
                $columns[$ci]['settings'] = array_replace_recursive(
                    $this->defaultColumnSettings($part),
                    is_array($columns[$ci]['settings'] ?? null) ? $columns[$ci]['settings'] : []
                );
                $columns[$ci]['settings']['width'] = $this->columnWidthFromLayoutPart($part);
                $fixed['columns']++;
            }

            $rows[$ri]['columns'] = $columns;
        }

        return $fixed;
    }

    /** @param array<int, mixed> $rows */
    private function resolve(array $rows, string $path, bool $byReference): mixed
    {
        if (!preg_match('/^rows\[(\d+)\](?:\.columns\[(\d+)\](?:\.addons\[(\d+)\])?)?$/', $path, $m)) {
            throw new \RuntimeException('Invalid path: ' . $path);
        }

        $ri = (int) $m[1];
        if (!isset($rows[$ri])) {
            throw new \RuntimeException('Path not found: ' . $path);
        }

        if (!isset($m[2]) || $m[2] === '') {
            return $rows[$ri];
        }

        $ci = (int) $m[2];
        if (!isset($rows[$ri]['columns'][$ci])) {
            throw new \RuntimeException('Path not found: ' . $path);
        }

        if (!isset($m[3]) || $m[3] === '') {
            return $rows[$ri]['columns'][$ci];
        }

        $ai = (int) $m[3];
        if (!isset($rows[$ri]['columns'][$ci]['addons'][$ai])) {
            throw new \RuntimeException('Path not found: ' . $path);
        }

        return $rows[$ri]['columns'][$ci]['addons'][$ai];
    }

    /** @param array<int, mixed> $rows */
    private function writeBack(array &$rows, string $path, array $node): void
    {
        if (!preg_match('/^rows\[(\d+)\](?:\.columns\[(\d+)\](?:\.addons\[(\d+)\])?)?$/', $path, $m)) {
            throw new \RuntimeException('Invalid path: ' . $path);
        }

        $ri = (int) $m[1];

        if (!isset($m[2]) || $m[2] === '') {
            $rows[$ri] = $node;
            return;
        }

        $ci = (int) $m[2];
        if (!isset($m[3]) || $m[3] === '') {
            $rows[$ri]['columns'][$ci] = $node;
            return;
        }

        $ai = (int) $m[3];
        $rows[$ri]['columns'][$ci]['addons'][$ai] = $node;
    }

    /** @param array<string, mixed> $target */
    private function setByDot(array &$target, string $field, mixed $value): void
    {
        $parts = explode('.', $field);
        $cursor = &$target;

        foreach ($parts as $i => $part) {
            if ($part === '') {
                throw new \RuntimeException('Invalid field path.');
            }
            if ($i === count($parts) - 1) {
                $cursor[$part] = $value;
                return;
            }
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor = &$cursor[$part];
        }
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private function previewSettings(array $settings, int $limit = 12): array
    {
        $preview = [];
        $keys = ['text', 'title', 'heading', 'content', 'background_color', 'color', 'type', 'appearance', 'url'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                $val = $settings[$key];
                if (is_array($val) && isset($val['url'])) {
                    $preview[$key] = $val['url'];
                } else {
                    $preview[$key] = is_scalar($val) ? $val : '[object]';
                }
            }
            if (count($preview) >= $limit) {
                break;
            }
        }

        if ($preview === []) {
            foreach (array_slice($settings, 0, $limit, true) as $k => $v) {
                $preview[$k] = is_scalar($v) ? $v : (is_array($v) ? '[array]' : '[object]');
            }
        }

        return $preview;
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private function regenerateIds(array $node): array
    {
        if (isset($node['id'])) {
            $node['id'] = $this->newId();
        }
        if (isset($node['columns']) && is_array($node['columns'])) {
            foreach ($node['columns'] as &$column) {
                if (!is_array($column)) {
                    continue;
                }
                if (isset($column['id'])) {
                    $column['id'] = $this->newId();
                }
                if (isset($column['addons']) && is_array($column['addons'])) {
                    foreach ($column['addons'] as &$addon) {
                        if (is_array($addon) && isset($addon['id'])) {
                            $addon['id'] = $this->newId();
                            if (isset($addon['settings']['instFormId'])) {
                                $addon['settings']['instFormId'] = $addon['id'];
                            }
                        }
                    }
                    unset($addon);
                }
            }
            unset($column);
        }

        return $node;
    }
}
