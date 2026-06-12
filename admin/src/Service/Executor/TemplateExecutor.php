<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\PathGuard;
use Joomla\Registry\Registry;

class TemplateExecutor
{
    private PathGuard $guard;

    public function __construct(?PathGuard $guard = null)
    {
        $this->guard = $guard ?? new PathGuard();
    }

    public function listInstalledTemplates(array $params): array
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['extension_id', 'name', 'element', 'enabled', 'client_id'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('template'))
            ->order('client_id, name');

        return ['templates' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function getTemplateStyle(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select('*')->from('#__template_styles')->where('id = ' . $id);
        $style = $db->setQuery($query)->loadAssoc();
        if (!$style) {
            throw new \RuntimeException('Template style not found.');
        }
        return $style;
    }

    public function createTemplateStyle(array $params): array
    {
        $data = (object) [
            'template'  => (string) ($params['template'] ?? ''),
            'title'     => (string) ($params['title'] ?? ''),
            'client_id' => (int) ($params['client_id'] ?? 0),
            'home'      => 0,
            'params'    => json_encode($params['params'] ?? []),
        ];

        $db = Factory::getDbo();
        $db->insertObject('#__template_styles', $data);

        return ['id' => (int) $db->insertid(), 'message' => 'Template style created.'];
    }

    public function updateTemplateStyle(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);
        $fields['id'] = $id;

        if (isset($fields['params']) && is_array($fields['params'])) {
            $db = Factory::getDbo();
            $current = (string) $db->setQuery(
                $db->getQuery(true)->select('params')->from('#__template_styles')->where('id = ' . $id)
            )->loadResult();
            $registry = new Registry($current ?: '');
            $registry->loadArray($fields['params']);
            $fields['params'] = $registry->toString();
        }

        $row = (object) $fields;
        Factory::getDbo()->updateObject('#__template_styles', $row, 'id');
        return ['id' => $id, 'message' => 'Template style updated.'];
    }

    public function deleteTemplateStyle(array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->delete('#__template_styles')->where('id = ' . $id)->where('home = 0');
        $db->setQuery($query)->execute();
        return ['id' => $id, 'message' => 'Template style deleted.'];
    }

    public function createTemplateOverride(array $params): array
    {
        $template = (string) ($params['template'] ?? $this->getActiveTemplate());
        $component = (string) ($params['component'] ?? 'com_content');
        $view = (string) ($params['view'] ?? 'article');
        $layout = (string) ($params['layout'] ?? 'default');
        $content = (string) ($params['content'] ?? "<?php\n// Template override for {$component}/{$view}\ndefined('_JEXEC') or die;\n?>");

        $relative = "templates/{$template}/html/{$component}/{$view}/{$layout}.php";
        $absolute = $this->guard->resolve(dirname($relative), true) . '/' . basename($relative);

        if (file_put_contents($absolute, $content) === false) {
            throw new \RuntimeException('Failed to create override file.');
        }

        return ['path' => $relative, 'message' => 'Template override created.'];
    }

    public function listTemplatePositions(array $params): array
    {
        $template = (string) ($params['template'] ?? $this->getActiveTemplate());
        $clientId = (int) ($params['client_id'] ?? 0);
        $xmlPath = $clientId === 1
            ? JPATH_ADMINISTRATOR . '/templates/' . $template . '/templateDetails.xml'
            : JPATH_ROOT . '/templates/' . $template . '/templateDetails.xml';

        if (!is_readable($xmlPath)) {
            throw new \RuntimeException('templateDetails.xml not found for ' . $template);
        }

        $xml = simplexml_load_file($xmlPath);
        $positions = [];
        if ($xml && isset($xml->positions->position)) {
            foreach ($xml->positions->position as $pos) {
                $positions[] = (string) $pos;
            }
        }

        return ['template' => $template, 'positions' => $positions];
    }

    public function setDefaultTemplateStyle(array $params): array
    {
        $styleId = (int) ($params['style_id'] ?? 0);
        $clientId = (int) ($params['client_id'] ?? 0);
        $dryRun = (bool) ($params['dry_run'] ?? false);

        if ($styleId <= 0) {
            throw new \RuntimeException('style_id is required.');
        }

        $db = Factory::getDbo();
        $style = $db->setQuery(
            $db->getQuery(true)->select(['id', 'template', 'title'])->from('#__template_styles')
                ->where('id = ' . $styleId)->where('client_id = ' . $clientId)
        )->loadAssoc();

        if (!$style) {
            throw new \RuntimeException('Template style not found.');
        }

        if ($dryRun) {
            return ['style_id' => $styleId, 'dry_run' => true, 'template' => $style['template']];
        }

        $db->setQuery(
            $db->getQuery(true)->update('#__template_styles')->set('home = 0')
                ->where('client_id = ' . $clientId)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)->update('#__template_styles')->set('home = 1')->where('id = ' . $styleId)
        )->execute();

        return [
            'style_id' => $styleId,
            'template' => $style['template'],
            'title'    => $style['title'],
            'message'  => 'Default template style set.',
        ];
    }

    private function getActiveTemplate(): string
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('template')
            ->from('#__template_styles')
            ->where('client_id = 0')
            ->where('home = 1');
        return (string) ($db->setQuery($query)->loadResult() ?: 'cassiopeia');
    }
}
