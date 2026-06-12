<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;
use Joomla\Registry\Registry;

class HelixExecutor
{
    private IntegrationDetector $detector;

    public function __construct(?IntegrationDetector $detector = null)
    {
        $this->detector = $detector ?? new IntegrationDetector();
    }

    public function getHelixLayout(array $params): array
    {
        $this->assertHelix();
        $styleId = (int) ($params['style_id'] ?? $this->getActiveStyleId());
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select(['id', 'template', 'title', 'params'])->from('#__template_styles')->where('id = ' . $styleId);
        $style = $db->setQuery($query)->loadAssoc();

        if (!$style) {
            throw new \RuntimeException('Template style not found.');
        }

        $registry = new Registry($style['params'] ?? '');
        return [
            'style_id' => $styleId,
            'template' => $style['template'],
            'params'   => $registry->toArray(),
        ];
    }

    public function updateHelixParams(array $params): array
    {
        $this->assertHelix();
        $styleId = (int) ($params['style_id'] ?? $this->getActiveStyleId());
        $newParams = (array) ($params['params'] ?? []);

        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('params')->from('#__template_styles')->where('id = ' . $styleId);
        $current = $db->setQuery($query)->loadResult();

        $registry = new Registry($current ?? '');
        $registry->loadArray($newParams);

        $db->setQuery(
            $db->getQuery(true)
                ->update('#__template_styles')
                ->set('params = ' . $db->quote($registry->toString()))
                ->where('id = ' . $styleId)
        )->execute();

        return ['style_id' => $styleId, 'message' => 'Helix parameters updated.'];
    }

    public function listHelixPositions(array $params): array
    {
        $this->assertHelix();
        $registry = new Registry($this->getHelixParams());
        $positions = [];

        foreach ($registry->toArray() as $key => $value) {
            if (str_contains($key, 'position') || str_contains($key, 'row')) {
                $positions[$key] = $value;
            }
        }

        return ['positions' => $positions];
    }

    public function getHelixMenuLayout(array $params): array
    {
        $this->assertHelix();
        $menuId = (int) ($params['menu_id'] ?? 0);
        if ($menuId <= 0) {
            throw new \RuntimeException('menu_id is required.');
        }

        $db = Factory::getDbo();
        $row = $db->setQuery(
            $db->getQuery(true)->select(['id', 'title', 'params'])->from('#__menu')->where('id = ' . $menuId)
        )->loadAssoc();

        if (!$row) {
            throw new \RuntimeException('Menu item not found.');
        }

        $paramsJson = json_decode((string) ($row['params'] ?? '{}'), true) ?: [];
        $layout = $paramsJson['helixultimatemenulayout'] ?? $paramsJson['helixmenulayout'] ?? null;

        return [
            'menu_id'    => $menuId,
            'title'      => $row['title'],
            'has_layout' => $layout !== null,
            'layout'     => is_string($layout) ? json_decode($layout, true) : $layout,
        ];
    }

    public function updateHelixMenuLayout(array $params): array
    {
        $this->assertHelix();
        $menuId = (int) ($params['menu_id'] ?? 0);
        $layout = $params['layout'] ?? null;

        if ($menuId <= 0 || $layout === null) {
            throw new \RuntimeException('menu_id and layout are required.');
        }

        $db = Factory::getDbo();
        $current = (string) $db->setQuery(
            $db->getQuery(true)->select('params')->from('#__menu')->where('id = ' . $menuId)
        )->loadResult();

        $registry = new Registry($current);
        $layoutValue = is_array($layout) ? json_encode($layout) : (string) $layout;
        $registry->set('helixultimatemenulayout', $layoutValue);

        $db->setQuery(
            $db->getQuery(true)->update('#__menu')
                ->set('params = ' . $db->quote($registry->toString()))
                ->where('id = ' . $menuId)
        )->execute();

        return ['menu_id' => $menuId, 'message' => 'Helix menu layout updated.'];
    }

    private function assertHelix(): void
    {
        if (!$this->detector->isInstalled('helixultimate')) {
            throw new \RuntimeException('Helix Ultimate template is not installed.');
        }
    }

    private function getActiveStyleId(): int
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('id')->from('#__template_styles')->where('client_id = 0')->where('home = 1');
        return (int) $db->setQuery($query)->loadResult();
    }

    private function getHelixParams(): string
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('params')->from('#__template_styles')->where('client_id = 0')->where('home = 1');
        return (string) ($db->setQuery($query)->loadResult() ?: '');
    }
}
