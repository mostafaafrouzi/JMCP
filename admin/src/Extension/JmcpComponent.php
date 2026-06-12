<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\Registry\Registry;
use Psr\Container\ContainerInterface;

class JmcpComponent extends MVCComponent implements BootableExtensionInterface
{
    private static ?ContainerInterface $serviceContainer = null;

    public function boot(ContainerInterface $container): void
    {
        self::$serviceContainer = $container;
    }

    public static function getServiceContainer(): ?ContainerInterface
    {
        return self::$serviceContainer;
    }

    public function getParams(): Registry
    {
        return ComponentHelper::getParams('com_jmcp');
    }
}
