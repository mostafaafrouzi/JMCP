<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Sp;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Loads SP Page Builder addon schemas from installed addon admin.php files.
 */
class SpAddonRegistry
{
    private bool $loaded = false;

    public function isInstalled(): bool
    {
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $name = $db->getPrefix() . 'sppagebuilder';

        return in_array($name, $tables, true);
    }

    public function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if (!is_file(JPATH_ROOT . '/components/com_sppagebuilder/builder/classes/base.php')) {
            throw new \RuntimeException('SP Page Builder component files not found.');
        }

        if (!class_exists('SpAddonsConfig')) {
            require_once JPATH_ROOT . '/components/com_sppagebuilder/builder/classes/config.php';
        }

        if (!class_exists('SpPgaeBuilderBase')) {
            require_once JPATH_ROOT . '/components/com_sppagebuilder/builder/classes/base.php';
        }

        \SpAddonsConfig::$addons = [];
        \SpPgaeBuilderBase::loadAddons();
        $this->loaded = true;
    }

    /** @return array<int, array<string, mixed>> */
    public function listAddons(): array
    {
        $this->ensureLoaded();
        $list = [];

        foreach (\SpAddonsConfig::getAddons() as $name => $config) {
            $list[] = [
                'name'     => (string) ($config['addon_name'] ?? $name),
                'type'     => (string) ($config['type'] ?? 'general'),
                'title'    => $this->plainText((string) ($config['title'] ?? $name)),
                'category' => (string) ($config['category'] ?? ''),
            ];
        }

        usort($list, fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $list;
    }

    /** @return array<string, mixed> */
    public function getAddonSchema(string $addonName): array
    {
        $this->ensureLoaded();
        $config = $this->findAddonConfig($addonName);

        if ($config === null) {
            throw new \RuntimeException('SP addon not found: ' . $addonName);
        }

        return $this->sanitizeJsonKeys([
            'name'     => (string) ($config['addon_name'] ?? $addonName),
            'type'     => (string) ($config['type'] ?? 'general'),
            'title'    => $this->plainText((string) ($config['title'] ?? $addonName)),
            'category' => (string) ($config['category'] ?? ''),
            'fields'   => $this->extractFields($config),
        ]);
    }

    /** @return array<string, mixed> */
    public function getDefaultSettings(string $addonName, ?int $templatePageId = null): array
    {
        if ($templatePageId !== null && $templatePageId > 0) {
            $blueprint = $this->findAddonNodeInPages($addonName, $templatePageId);
            if ($blueprint !== null && isset($blueprint['settings']) && is_array($blueprint['settings'])) {
                $merged = $blueprint['settings'];
                $schemaDefaults = $this->defaultsFromSchema($addonName);

                return array_replace_recursive($schemaDefaults, $merged);
            }

            $template = $this->findAddonTemplateInPages($addonName, $templatePageId);
            if ($template !== null) {
                return array_replace_recursive($this->defaultsFromSchema($addonName), $template);
            }
        }

        return $this->defaultsFromSchema($addonName);
    }

    /** @return array<string, mixed> */
    public function getAddonBlueprint(string $addonName, ?int $templatePageId = null): array
    {
        $this->ensureLoaded();
        $schema = $this->getAddonSchema($addonName);

        if ($templatePageId !== null && $templatePageId > 0) {
            $node = $this->findAddonNodeInPages($addonName, $templatePageId);

            if ($node !== null) {
                return [
                    'addon_name' => $schema['name'],
                    'type'       => $node['type'] ?? $schema['type'],
                    'settings'   => $node['settings'] ?? [],
                    'source'     => 'template_page',
                ];
            }
        }

        return [
            'addon_name' => $schema['name'],
            'type'       => $schema['type'],
            'settings'   => $this->defaultsFromSchema($addonName),
            'source'     => 'schema',
        ];
    }

    /** @return array{content: string[], style: string[], advanced: string[], repeatable: string[]} */
    public function getFieldGroups(string $addonName): array
    {
        $schema = $this->getAddonSchema($addonName);
        $groups = [
            'content'    => [],
            'style'      => [],
            'advanced'   => [],
            'repeatable' => [],
        ];

        foreach ($schema['fields'] as $field) {
            if (!is_array($field) || !isset($field['key'])) {
                continue;
            }
            $key = (string) $field['key'];
            $type = (string) ($field['type'] ?? '');
            $group = strtolower((string) ($field['group'] ?? ''));

            if ($type === 'repeatable') {
                $groups['repeatable'][] = $key;
                continue;
            }

            if (str_starts_with($key, 'global_') || in_array($group, ['style', 'link_type_style', 'icon'], true)) {
                $groups['style'][] = $key;
            } elseif (in_array($group, ['advanced', 'options', 'animation', 'interaction'], true)) {
                $groups['advanced'][] = $key;
            } else {
                $groups['content'][] = $key;
            }
        }

        return $groups;
    }

    /** @return array<string, mixed> */
    public function getRepeatableItemDefaults(string $addonName, string $repeatableKey, ?int $templatePageId = null): array
    {
        $blueprint = $this->getAddonBlueprint($addonName, $templatePageId);
        $settings = $blueprint['settings'] ?? [];
        $items = $settings[$repeatableKey] ?? null;

        if (is_array($items) && isset($items[0]) && is_array($items[0])) {
            return $items[0];
        }

        $config = $this->findAddonConfig($addonName);
        if ($config === null) {
            return [];
        }

        return $this->extractRepeatableAttrDefaults($config, $repeatableKey);
    }

    /** @return array<string, mixed>|null */
    public function findAddonNodeInPages(string $addonName, ?int $pageId): ?array
    {
        $needle = strtolower($addonName);

        if ($pageId === null || $pageId <= 0) {
            return null;
        }

        return $this->scanRowsForAddonNode($this->loadPageContent($pageId), $needle);
    }

    /** @param array<string, mixed> $config */
    private function extractRepeatableAttrDefaults(array $config, string $repeatableKey): array
    {
        $groups = $config['settings'] ?? $config['attr'] ?? [];
        if (!is_array($groups)) {
            return [];
        }

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $fields = $group['fields'] ?? $group;
            if (!is_array($fields) || !isset($fields[$repeatableKey]) || !is_array($fields[$repeatableKey])) {
                continue;
            }
            $repeatable = $fields[$repeatableKey];
            $attr = $repeatable['attr'] ?? [];
            if (!is_array($attr)) {
                return [];
            }
            $defaults = [];
            foreach ($attr as $name => $field) {
                if (is_array($field) && array_key_exists('std', $field)) {
                    $defaults[(string) $name] = $field['std'];
                }
            }

            return $defaults;
        }

        return [];
    }

    /** @param array<int, mixed> $rows @return array<string, mixed>|null */
    private function scanRowsForAddonNode(array $rows, string $needle): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                foreach ($column['addons'] ?? [] as $addon) {
                    if (!is_array($addon)) {
                        continue;
                    }
                    if (strtolower((string) ($addon['name'] ?? '')) === $needle) {
                        return $addon;
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findAddonConfig(string $addonName): ?array
    {
        $this->ensureLoaded();
        $addons = \SpAddonsConfig::getAddons();
        $needle = strtolower($addonName);

        foreach ($addons as $key => $config) {
            $name = strtolower((string) ($config['addon_name'] ?? $key));
            if ($name === $needle) {
                return $config;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $config @return array<int, array<string, mixed>> */
    private function extractFields(array $config): array
    {
        $fields = [];
        $groups = $config['settings'] ?? $config['attr'] ?? [];

        if (!is_array($groups)) {
            return $fields;
        }

        foreach ($groups as $groupName => $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupFields = $group['fields'] ?? $group;
            if (!is_array($groupFields)) {
                continue;
            }
            foreach ($groupFields as $fieldName => $field) {
                if (!is_array($field) || !isset($field['type'])) {
                    continue;
                }
                $fields[] = [
                    'key'     => (string) $fieldName,
                    'type'    => (string) $field['type'],
                    'group'   => (string) $groupName,
                    'default' => $field['std'] ?? null,
                    'values'  => $field['values'] ?? null,
                ];
            }
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    private function defaultsFromSchema(string $addonName): array
    {
        $schema = $this->getAddonSchema($addonName);
        $settings = [];

        foreach ($schema['fields'] as $field) {
            if (!is_array($field) || !isset($field['key'])) {
                continue;
            }
            if (array_key_exists('default', $field) && $field['default'] !== null) {
                $settings[(string) $field['key']] = $field['default'];
            }
        }

        return $settings;
    }

    /** @return array<string, mixed>|null */
    private function findAddonTemplateInPages(string $addonName, ?int $pageId): ?array
    {
        if ($pageId === null || $pageId <= 0) {
            return null;
        }

        $needle = strtolower($addonName);
        $content = $this->loadPageContent($pageId);

        return $this->scanRowsForAddon($content, $needle);
    }

    /** @param array<int, mixed> $rows @return array<string, mixed>|null */
    private function scanRowsForAddon(array $rows, string $needle): ?array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                foreach ($column['addons'] ?? [] as $addon) {
                    if (!is_array($addon)) {
                        continue;
                    }
                    $name = strtolower((string) ($addon['name'] ?? ''));
                    if ($name === $needle && isset($addon['settings']) && is_array($addon['settings'])) {
                        return $addon['settings'];
                    }
                }
            }
        }

        return null;
    }

    /** @return array<int, mixed> */
    private function loadPageContent(int $pageId): array
    {
        $db = Factory::getDbo();
        $content = $db->setQuery(
            $db->getQuery(true)
                ->select('content')
                ->from('#__sppagebuilder')
                ->where('id = ' . $pageId)
        )->loadResult();

        $decoded = json_decode((string) $content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (str_starts_with(trim($value), 'COM_')) {
            return $value;
        }

        return strip_tags($value);
    }

    /** @param array<mixed> $data @return array<mixed> */
    private function sanitizeJsonKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $safeKey = ($key === '' || $key === null) ? '_default' : (string) $key;
            $out[$safeKey] = is_array($value) ? $this->sanitizeJsonKeys($value) : $value;
        }

        return $out;
    }
}
