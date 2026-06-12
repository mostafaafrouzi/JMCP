<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\PathGuard;

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
            $fields['params'] = json_encode($fields['params']);
        }

        Factory::getDbo()->updateObject('#__template_styles', (object) $fields, 'id');
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
