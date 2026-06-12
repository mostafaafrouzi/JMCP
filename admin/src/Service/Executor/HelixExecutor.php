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
