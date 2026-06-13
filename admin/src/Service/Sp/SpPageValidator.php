<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Sp;

defined('_JEXEC') or die;

class SpPageValidator
{
    public function __construct(
        private SpPageTree $tree = new SpPageTree(),
        private SpAddonRegistry $registry = new SpAddonRegistry()
    ) {
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{valid: bool, errors: string[], warnings: string[], row_count: int, addon_count: int}
     */
    public function validate(array $rows): array
    {
        $errors = $this->tree->validateStructure($rows);
        $warnings = [];
        $addonCount = 0;

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
                    $addonCount++;
                    $name = (string) ($addon['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    try {
                        $this->registry->getAddonSchema($name);
                        $groups = $this->registry->getFieldGroups($name);
                        foreach ($groups['repeatable'] as $repeatableKey) {
                            $items = $addon['settings'][$repeatableKey] ?? null;
                            if (!is_array($items) || $items === []) {
                                $warnings[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . ']: empty repeatable "' . $repeatableKey . '"';
                                continue;
                            }
                            foreach ($items as $ii => $item) {
                                if (!is_array($item)) {
                                    $warnings[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . '].settings.' . $repeatableKey . '[' . $ii . ']: invalid item';
                                    continue;
                                }
                                if (!isset($item['id']) || (string) $item['id'] === '') {
                                    $warnings[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . '].settings.' . $repeatableKey . '[' . $ii . ']: missing id';
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $warnings[] = 'rows[' . $ri . '].columns[' . $ci . '].addons[' . $ai . ']: unknown addon "' . $name . '"';
                    }
                }
            }
        }

        return [
            'valid'       => $errors === [],
            'errors'      => $errors,
            'warnings'    => $warnings,
            'row_count'   => count($rows),
            'addon_count' => $addonCount,
        ];
    }
}
