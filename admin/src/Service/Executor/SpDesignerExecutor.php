<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Component\Jmcp\Administrator\Service\JoomlaMediaService;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpAddonRegistry;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpPageSaveService;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpPageTree;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpPageValidator;
use Joomla\Component\Jmcp\Administrator\Service\Sp\SpSectionLibrary;

/**
 * SP Page Builder designer tools — structured page tree editing (phases 1–3).
 */
class SpDesignerExecutor
{
    private SpAddonRegistry $addons;
    private SpPageTree $tree;
    private SpPageValidator $validator;
    private SpPageExecutor $pages;
    private SpPageSaveService $saveService;
    private SpSectionLibrary $sections;
    private JoomlaMediaService $media;

    public function __construct(
        ?SpAddonRegistry $addons = null,
        ?SpPageTree $tree = null,
        ?SpPageValidator $validator = null,
        ?SpPageExecutor $pages = null,
        ?SpPageSaveService $saveService = null,
        ?SpSectionLibrary $sections = null,
        ?JoomlaMediaService $media = null
    ) {
        $this->addons      = $addons ?? new SpAddonRegistry();
        $this->tree        = $tree ?? new SpPageTree();
        $this->validator   = $validator ?? new SpPageValidator($this->tree, $this->addons);
        $this->pages       = $pages ?? new SpPageExecutor();
        $this->saveService = $saveService ?? new SpPageSaveService();
        $this->sections    = $sections ?? new SpSectionLibrary($this->tree);
        $this->media       = $media ?? new JoomlaMediaService();
    }

    public function listSpAddons(array $params): array
    {
        $this->assertSp();

        return [
            'installed' => true,
            'count'     => count($this->addons->listAddons()),
            'addons'    => $this->addons->listAddons(),
        ];
    }

    public function getSpAddonSchema(array $params): array
    {
        $this->assertSp();
        $name = $this->requireAddonName($params);

        return array_merge(
            $this->addons->getAddonSchema($name),
            ['field_groups' => $this->addons->getFieldGroups($name)]
        );
    }

    public function getSpAddonBlueprint(array $params): array
    {
        $this->assertSp();
        $name = $this->requireAddonName($params);
        $templatePageId = isset($params['template_page_id']) ? (int) $params['template_page_id'] : null;

        return array_merge(
            $this->addons->getAddonBlueprint($name, $templatePageId),
            ['field_groups' => $this->addons->getFieldGroups($name)]
        );
    }

    public function getSpPageTree(array $params): array
    {
        $page = $this->loadPage((int) ($params['page_id'] ?? $params['id'] ?? 0));
        $rows = $this->tree->decode((string) ($page['content'] ?? '[]'));

        return [
            'page_id'   => (int) $page['id'],
            'title'     => $page['title'] ?? '',
            'row_count' => count($rows),
            'nodes'     => $this->tree->buildTree($rows, (bool) ($params['field_preview'] ?? true)),
        ];
    }

    public function getSpNode(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $path = trim((string) ($params['path'] ?? ''));
        if ($path === '') {
            throw new \RuntimeException('path is required.');
        }

        $rows = $this->loadContentRows($pageId);
        $node = $this->tree->getNode($rows, $path);
        if (!is_array($node)) {
            throw new \RuntimeException('Node not found or not an object.');
        }

        return ['page_id' => $pageId, 'path' => $path, 'node' => $node];
    }

    public function findSpAddonsOnPage(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $addonName = isset($params['addon_name']) ? trim((string) $params['addon_name']) : null;
        $rows = $this->loadContentRows($pageId);

        return [
            'page_id' => $pageId,
            'matches' => $this->tree->findAddonPaths($rows, $addonName !== '' ? $addonName : null),
        ];
    }

    public function setSpAddonField(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $path = trim((string) $params['path']);
            $field = trim((string) $params['field']);
            $this->tree->setAddonField($rows, $path, $field, $params['value']);

            return ['path' => $path, 'field' => $field, 'value' => $params['value']];
        });
    }

    public function setSpRowField(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $path = trim((string) $params['path']);
            $field = trim((string) $params['field']);
            $this->tree->setRowField($rows, $path, $field, $params['value']);

            return ['path' => $path, 'field' => $field, 'value' => $params['value']];
        });
    }

    public function setSpColumnField(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $path = trim((string) $params['path']);
            $field = trim((string) $params['field']);
            $this->tree->setColumnField($rows, $path, $field, $params['value']);

            return ['path' => $path, 'field' => $field, 'value' => $params['value']];
        });
    }

    public function setSpAddonStyleTab(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $path = trim((string) ($params['path'] ?? ''));
        $tab = strtolower(trim((string) ($params['tab'] ?? 'content')));
        $fields = $params['fields'] ?? null;

        if ($path === '' || !is_array($fields) || $fields === []) {
            throw new \RuntimeException('path and fields object are required.');
        }

        $addon = $this->tree->getNode($this->loadContentRows($pageId), $path);
        if (!is_array($addon) || !isset($addon['name'])) {
            throw new \RuntimeException('Path must point to an addon.');
        }

        $groups = $this->addons->getFieldGroups((string) $addon['name']);
        $allowed = match ($tab) {
            'style'    => $groups['style'],
            'advanced' => $groups['advanced'],
            'content'  => $groups['content'],
            default    => throw new \RuntimeException('tab must be content, style, or advanced.'),
        };

        $applied = [];
        foreach ($fields as $key => $value) {
            $key = (string) $key;
            if ($allowed !== [] && !in_array($key, $allowed, true) && !str_starts_with($key, 'global_')) {
                continue;
            }
            $applied[$key] = $value;
        }

        if ($applied === []) {
            throw new \RuntimeException('No valid fields for tab "' . $tab . '".');
        }

        return $this->mutatePage($params, function (array &$rows) use ($path, $applied, $tab): array {
            $this->tree->setAddonFields($rows, $path, $applied);

            return ['path' => $path, 'tab' => $tab, 'fields' => array_keys($applied)];
        });
    }

    public function bulkSetSpAddonField(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $field = trim((string) ($params['field'] ?? ''));
        $paths = $params['paths'] ?? null;
        if ($field === '' || !is_array($paths) || $paths === []) {
            throw new \RuntimeException('field and paths array are required.');
        }
        if (!array_key_exists('value', $params)) {
            throw new \RuntimeException('value is required.');
        }

        $dryRun = (bool) ($params['dry_run'] ?? false);
        $rows = $this->loadContentRows($pageId);
        $updated = [];

        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if (!$dryRun) {
                $this->tree->setAddonField($rows, $path, $field, $params['value']);
            }
            $updated[] = $path;
        }

        if (!$dryRun) {
            $this->saveContent($pageId, $rows, $this->loadPage($pageId));
        }

        return [
            'page_id' => $pageId,
            'field'   => $field,
            'paths'   => $updated,
            'dry_run' => $dryRun,
            'message' => $dryRun ? 'Dry run: bulk update validated.' : 'Bulk addon field updated.',
        ];
    }

    public function addSpRow(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $path = $this->tree->addRow(
                $rows,
                trim((string) ($params['layout'] ?? '12')),
                isset($params['after_index']) ? (int) $params['after_index'] : null,
                is_array($params['settings'] ?? null) ? $params['settings'] : []
            );

            return ['path' => $path, 'layout' => (string) ($params['layout'] ?? '12')];
        });
    }

    public function addSpAddon(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $columnPath = trim((string) ($params['column_path'] ?? $params['path'] ?? ''));
        $addonName = $this->requireAddonName($params);
        $this->assertSp();

        $schema = $this->addons->getAddonSchema($addonName);
        $templatePageId = isset($params['template_page_id']) ? (int) $params['template_page_id'] : 0;
        $blueprint = $this->addons->getAddonBlueprint(
            $addonName,
            $templatePageId > 0 ? $templatePageId : null
        );
        $settings = $blueprint['settings'] ?? $this->addons->getDefaultSettings($addonName, $templatePageId > 0 ? $templatePageId : null);

        $fieldOverrides = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        if ($fieldOverrides !== []) {
            $settings = array_replace_recursive($settings, $fieldOverrides);
        }
        if (is_array($params['settings'] ?? null)) {
            $settings = array_replace_recursive($settings, $params['settings']);
        }
        $settings = $this->finalizeAddonSettings($addonName, $settings, $fieldOverrides);

        return $this->mutatePage($params, function (array &$rows) use ($columnPath, $schema, $settings, $addonName, $blueprint): array {
            $path = $this->tree->addAddon(
                $rows,
                $columnPath,
                (string) $schema['name'],
                (string) ($blueprint['type'] ?? $schema['type'] ?? 'general'),
                $settings,
                (string) ($schema['title'] ?? $addonName),
                null
            );

            return ['path' => $path, 'addon_name' => $addonName];
        }, $pageId);
    }

    public function addSpRepeatableItem(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $addonPath = trim((string) ($params['addon_path'] ?? $params['path'] ?? ''));
        $repeatableKey = trim((string) ($params['repeatable_key'] ?? ''));
        if ($addonPath === '') {
            throw new \RuntimeException('addon_path is required.');
        }

        $addon = $this->tree->getNode($this->loadContentRows($pageId), $addonPath);
        if (!is_array($addon) || !isset($addon['name'])) {
            throw new \RuntimeException('addon_path must point to an addon.');
        }

        if ($repeatableKey === '') {
            $groups = $this->addons->getFieldGroups((string) $addon['name']);
            $repeatableKey = $groups['repeatable'][0] ?? '';
        }
        if ($repeatableKey === '') {
            throw new \RuntimeException('repeatable_key is required for this addon.');
        }

        $itemFields = is_array($params['item'] ?? null) ? $params['item'] : [];
        if ($itemFields === []) {
            $itemFields = $this->addons->getRepeatableItemDefaults(
                (string) $addon['name'],
                $repeatableKey,
                isset($params['template_page_id']) ? (int) $params['template_page_id'] : null
            );
        }

        return $this->mutatePage($params, function (array &$rows) use ($addonPath, $repeatableKey, $itemFields): array {
            $path = $this->tree->addRepeatableItem($rows, $addonPath, $repeatableKey, $itemFields);

            return ['path' => $path, 'repeatable_key' => $repeatableKey];
        }, $pageId);
    }

    public function deleteSpNode(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $path = trim((string) $params['path']);
            $this->tree->deleteNode($rows, $path);

            return ['path' => $path];
        });
    }

    public function cloneSpRow(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $rowPath = trim((string) ($params['row_path'] ?? $params['path'] ?? ''));
            $newPath = $this->tree->cloneRow($rows, $rowPath, isset($params['after_index']) ? (int) $params['after_index'] : null);

            return ['source' => $rowPath, 'path' => $newPath];
        });
    }

    public function cloneSpAddon(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $addonPath = trim((string) ($params['addon_path'] ?? $params['path'] ?? ''));
            $newPath = $this->tree->cloneAddon($rows, $addonPath, isset($params['after_index']) ? (int) $params['after_index'] : null);

            return ['source' => $addonPath, 'path' => $newPath];
        });
    }

    public function moveSpNode(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows) use ($params): array {
            $from = trim((string) ($params['from_path'] ?? ''));
            $to = trim((string) ($params['to_column_path'] ?? ''));
            $newPath = $this->tree->moveNode($rows, $from, $to, isset($params['to_index']) ? (int) $params['to_index'] : null);

            return ['from_path' => $from, 'path' => $newPath];
        });
    }

    public function insertSpSection(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $sourcePageId = (int) ($params['source_page_id'] ?? $params['template_page_id'] ?? 0);
        $rowIndex = (int) ($params['row_index'] ?? 0);
        if ($sourcePageId <= 0) {
            throw new \RuntimeException('source_page_id and row_index are required.');
        }

        $rowData = $this->sections->getRowPreset($sourcePageId, $rowIndex);

        return $this->mutatePage($params, function (array &$rows) use ($rowData, $params): array {
            $path = $this->tree->insertRowFromData(
                $rows,
                $rowData,
                isset($params['after_index']) ? (int) $params['after_index'] : null
            );

            return ['path' => $path, 'source_page_id' => (int) ($params['source_page_id'] ?? 0), 'row_index' => (int) ($params['row_index'] ?? 0)];
        }, $pageId);
    }

    public function listSpSectionPresets(array $params): array
    {
        $this->assertSp();

        return [
            'presets' => $this->sections->listPresets((int) ($params['limit'] ?? 30)),
        ];
    }

    public function validateSpPage(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);

        return array_merge(['page_id' => $pageId], $this->validator->validate($this->loadContentRows($pageId)));
    }

    public function repairSpPageLayout(array $params): array
    {
        return $this->mutatePage($params, function (array &$rows): array {
            $fixed = $this->tree->repairLayout($rows);

            return array_merge($fixed, ['message' => 'Column widths and row settings repaired.']);
        });
    }

    public function previewSpPage(array $params): array
    {
        $page = $this->loadPage((int) ($params['page_id'] ?? $params['id'] ?? 0));
        $id = (int) $page['id'];
        $root = rtrim(Uri::root(), '/');

        return [
            'page_id'      => $id,
            'title'        => $page['title'] ?? '',
            'published'    => (int) ($page['published'] ?? 0),
            'preview_url'  => $root . '/index.php?option=com_sppagebuilder&view=page&id=' . $id,
            'admin_url'    => $root . '/administrator/index.php?option=com_sppagebuilder&view=form&id=' . $id,
        ];
    }

    public function setSpPageCss(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $css = $this->resolveSpPageCss($params);

        if ($dryRun) {
            return [
                'page_id' => $pageId,
                'dry_run' => true,
                'bytes'   => strlen($css),
                'message' => 'Dry run: CSS update validated.',
            ];
        }

        $result = $this->saveService->saveCssOnly($pageId, $css);

        return array_merge($result, ['page_id' => $pageId]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveSpPageCss(array $params): string
    {
        $mediaPath = trim((string) ($params['media_path'] ?? $params['css_path'] ?? ''));
        if ($mediaPath !== '') {
            $absolute = $this->media->resolveAbsolute($mediaPath);
            if (!is_file($absolute)) {
                throw new \RuntimeException('CSS media file not found: ' . $mediaPath);
            }
            $css = file_get_contents($absolute);
            if ($css === false || $css === '') {
                throw new \RuntimeException('Failed to read CSS media file: ' . $mediaPath);
            }

            return $css;
        }

        if (!array_key_exists('css', $params)) {
            throw new \RuntimeException('css or media_path is required.');
        }

        return (string) $params['css'];
    }

    public function saveSpPageDesign(array $params): array
    {
        $pageId = (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $rows = $this->loadContentRows($pageId);
        $validation = $this->validator->validate($rows);

        if (!$validation['valid']) {
            throw new \RuntimeException('SP page validation failed: ' . implode('; ', $validation['errors']));
        }
        if ($dryRun) {
            return ['page_id' => $pageId, 'dry_run' => true, 'validation' => $validation, 'message' => 'Dry run: save validated.'];
        }

        $page = $this->loadPage($pageId);
        $result = $this->saveContent($pageId, $rows, $page);

        return array_merge($result, ['page_id' => $pageId, 'validation' => $validation]);
    }

    public function createSpPageFromTemplate(array $params): array
    {
        $sourceId = (int) ($params['template_page_id'] ?? $params['source_id'] ?? 0);
        $title = trim((string) ($params['title'] ?? ''));
        if ($sourceId <= 0 || $title === '') {
            throw new \RuntimeException('template_page_id and title are required.');
        }

        $source = $this->pages->getSpPage(['id' => $sourceId]);
        $result = $this->pages->saveSpPage([
            'title'     => $title,
            'content'   => (string) ($source['content'] ?? '[]'),
            'layout'    => (string) ($source['text'] ?? '[]'),
            'css'       => (string) ($source['css'] ?? ''),
            'published' => (int) ($params['published'] ?? 0),
            'language'  => (string) ($params['language'] ?? ($source['language'] ?? '*')),
            'dry_run'   => (bool) ($params['dry_run'] ?? false),
        ]);

        return array_merge($result, [
            'template_page_id' => $sourceId,
            'message'          => 'SP page created from template.',
        ]);
    }

    public function setSpAddonMedia(array $params): array
    {
        $field = trim((string) ($params['field'] ?? 'image'));
        $src = trim((string) ($params['src'] ?? $params['path'] ?? ''));
        if ($src === '') {
            throw new \RuntimeException('src (media path) is required.');
        }

        $value = is_array($params['value'] ?? null)
            ? $params['value']
            : ['src' => $src, 'height' => '', 'width' => '', 'alt' => (string) ($params['alt'] ?? '')];

        $params['field'] = $field;
        $params['value'] = $value;

        return $this->setSpAddonField($params);
    }

    /**
     * @param callable(array<int,mixed>):array<string,mixed> $mutator
     * @return array<string,mixed>
     */
    private function mutatePage(array $params, callable $mutator, ?int $pageId = null): array
    {
        $pageId = $pageId ?? (int) ($params['page_id'] ?? $params['id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);
        $rows = $this->loadContentRows($pageId);
        $result = $mutator($rows);

        if (!$dryRun) {
            $page = $this->loadPage($pageId);
            $this->saveContent($pageId, $rows, $page);
        }

        return array_merge([
            'page_id' => $pageId,
            'dry_run' => $dryRun,
            'message' => $dryRun ? 'Dry run: change validated.' : 'SP page updated.',
        ], $result);
    }

    private function requireAddonName(array $params): string
    {
        $name = trim((string) ($params['addon_name'] ?? $params['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('addon_name is required.');
        }

        return $name;
    }

    /** @return array<string, mixed> */
    private function loadPage(int $pageId): array
    {
        if ($pageId <= 0) {
            throw new \RuntimeException('page_id is required.');
        }

        return $this->pages->getSpPage(['id' => $pageId]);
    }

    /** @return array<int, mixed> */
    private function loadContentRows(int $pageId): array
    {
        return $this->tree->decode((string) ($this->loadPage($pageId)['content'] ?? '[]'));
    }

    /** @param array<int, mixed> $rows @param array<string, mixed> $page */
    private function saveContent(int $pageId, array $rows, array $page): array
    {
        $validation = $this->validator->validate($rows);
        if (!$validation['valid']) {
            throw new \RuntimeException('SP page validation failed: ' . implode('; ', $validation['errors']));
        }

        $content = $this->tree->encode($rows);

        return $this->saveService->save($pageId, [
            'title'   => (string) ($page['title'] ?? ''),
            'content' => $content,
            'text'    => $content,
            'css'     => (string) ($page['css'] ?? ''),
            'published' => (int) ($page['published'] ?? 0),
            'language'  => (string) ($page['language'] ?? '*'),
        ]);
    }

    private function assertSp(): void
    {
        if (!$this->addons->isInstalled()) {
            throw new \RuntimeException('SP Page Builder is not installed on this site.');
        }
    }

    /**
     * Normalize addon settings after field merge so template pollution does not leak to the frontend.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function finalizeAddonSettings(string $addonName, array $settings, array $fields): array
    {
        if ($addonName === 'text_block') {
            if (array_key_exists('text', $fields) && !array_key_exists('title', $fields)) {
                $settings['title'] = '';
            }
        }

        if ($addonName === 'heading') {
            if (array_key_exists('title', $fields)) {
                if (!array_key_exists('title_color', $fields)) {
                    $settings['title_color'] = ['color' => '#1d1d1f', 'type' => 'solid'];
                }
                if (!array_key_exists('global_use_animation', $fields)) {
                    $settings['global_use_animation'] = false;
                }
                if (!array_key_exists('heading_typography', $fields)) {
                    $settings['heading_typography'] = [
                        'font'           => '',
                        'weight'         => '600',
                        'uppercase'      => false,
                        'underline'      => false,
                        'italic'         => false,
                        'type'           => 'system',
                        'size'           => ['xl' => ['value' => '56', 'unit' => 'px']],
                        'line_height'    => ['xl' => ['value' => '60', 'unit' => 'px']],
                        'letter_spacing' => ['xl' => ['value' => '', 'unit' => 'px']],
                    ];
                }
                if (!array_key_exists('alignment', $fields)) {
                    $settings['alignment'] = 'center';
                }
            }
        }

        if ($addonName === 'button' && (($fields['type'] ?? $settings['type'] ?? '') === 'link')) {
            if (!array_key_exists('background_color', $fields)) {
                $settings['background_color'] = 'transparent';
            }
            if (!array_key_exists('color', $fields)) {
                $settings['color'] = '#0071e3';
            }
        }

        return $settings;
    }
}
