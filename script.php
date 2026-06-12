<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jmcp\Administrator\Service\DatabaseInstaller;
use Joomla\Registry\Registry;

/**
 * Installation script class for com_jmcp
 */
class Com_JmcpInstallerScript
{
    /**
     * Called after installation
     *
     * @param   object  $adapter  The installer adapter class
     *
     * @return  boolean  True on success
     */
    public function install($adapter): bool
    {
        return true;
    }

    /**
     * Called after uninstallation
     *
     * @param   object  $adapter  The installer adapter class
     */
    public function uninstall($adapter): void
    {
    }

    /**
     * Called after update
     *
     * @param   object  $adapter  The installer adapter class
     *
     * @return  boolean  True on success
     */
    public function update($adapter): bool
    {
        $this->ensureDatabaseTables();
        return true;
    }

    /**
     * Called before install, update or uninstall starts
     *
     * @param   string  $type     The action type
     * @param   object  $adapter  The installer adapter class
     *
     * @return  boolean  True on success
     */
    public function preflight(string $type, $adapter): bool
    {
        return true;
    }

    /**
     * Called after install, update or uninstall ends
     *
     * @param   string  $type     The action type
     * @param   object  $adapter  The installer adapter class
     *
     * @return  boolean  True on success
     */
    public function postflight(string $type, $adapter): bool
    {
        if ($type === 'install' || $type === 'update') {
            $this->ensureDatabaseTables();
            $this->ensureBearerToken();
            $this->ensureDomainLockHost();

            echo '<div class="alert alert-success">';
            echo '<h3>' . Text::_('COM_JMCP_INSTALL_SUCCESS_TITLE') . '</h3>';
            echo '<p>' . Text::_('COM_JMCP_INSTALL_SUCCESS_DESC') . '</p>';
            echo '</div>';
        }

        return true;
    }

    /**
     * Generate a bearer token on first install when none is configured.
     */
    private function ensureDomainLockHost(): void
    {
        $params = ComponentHelper::getParams('com_jmcp');
        if (!empty($params->get('domain_lock_host'))) {
            return;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['extension_id', 'params'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jmcp'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $row = $db->setQuery($query)->loadObject();
        if (!$row) {
            return;
        }

        $registry = new Registry($row->params ?? '');
        $registry->set('domain_lock_host', \Joomla\CMS\Uri\Uri::getInstance()->getHost());

        $update = new \stdClass();
        $update->extension_id = (int) $row->extension_id;
        $update->params       = $registry->toString();
        $db->updateObject('#__extensions', $update, 'extension_id');
    }

    private function ensureDatabaseTables(): void
    {
        try {
            (new DatabaseInstaller())->ensureTables();
        } catch (\Throwable $e) {
        }
    }

    private function ensureBearerToken(): void
    {
        $params = ComponentHelper::getParams('com_jmcp');

        if (!empty($params->get('mcp_bearer_token'))) {
            return;
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['extension_id', 'params'])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jmcp'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $row = $db->setQuery($query)->loadObject();

        if (!$row) {
            return;
        }

        $registry = new Registry($row->params ?? '');
        $registry->set('mcp_bearer_token', bin2hex(random_bytes(32)));

        $update = new \stdClass();
        $update->extension_id = (int) $row->extension_id;
        $update->params       = $registry->toString();
        $db->updateObject('#__extensions', $update, 'extension_id');
    }
}
