<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Read/write com_jmcp component parameters in #__extensions.
 */
class ComponentParamsService
{
    public function get(): Registry
    {
        $row = $this->loadRow();
        return new Registry($row->params ?? '');
    }

    public function save(Registry $params): void
    {
        $row = $this->loadRow();
        $db  = Factory::getDbo();

        $update = new \stdClass();
        $update->extension_id = (int) $row->extension_id;
        $update->params       = $params->toString();
        $db->updateObject('#__extensions', $update, 'extension_id');
    }

    public function set(string $key, mixed $value): void
    {
        $params = $this->get();
        $params->set($key, $value);
        $this->save($params);
    }

    private function loadRow(): object
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['extension_id', 'params'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jmcp'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $row = $db->setQuery($query)->loadObject();
        if (!$row) {
            throw new \RuntimeException('com_jmcp extension record not found.');
        }

        return $row;
    }
}
