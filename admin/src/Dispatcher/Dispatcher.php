<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

class Dispatcher extends ComponentDispatcher
{
    public function dispatch(): void
    {
        $task = $this->input->get('task', '');
        
        if (str_contains($task, '.')) {
            [$name, $action] = explode('.', $task, 2);
        } else {
            $name = $task ?: 'display';
            $action = 'display';
        }
        
        $name = ucfirst(strtolower($name));
        
        if ($name === '') {
            $name = 'Display';
        }
        
        $controller = $this->getController($name, 'Administrator', ['task' => $action]);
        $controller->execute($action);
        $controller->redirect();
    }
}
