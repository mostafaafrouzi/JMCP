<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory as MVCFactoryProvider;
use Joomla\CMS\Extension\Service\Provider\RouterFactory as RouterFactoryProvider;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Component\Jmcp\Administrator\Dispatcher\Dispatcher;
use Joomla\Component\Jmcp\Administrator\Extension\JmcpComponent;
use Joomla\Component\Jmcp\Administrator\Service\AuthService;
use Joomla\Component\Jmcp\Administrator\Service\CacheService;
use Joomla\Component\Jmcp\Administrator\Service\JoomlaCache;
use Joomla\Component\Jmcp\Administrator\Service\MetricsService;
use Joomla\Component\Jmcp\Administrator\Service\PolicyService;
use Joomla\Component\Jmcp\Administrator\Service\RateLimiter;
use Joomla\Component\Jmcp\Administrator\Service\RpcService;
use Joomla\Component\Jmcp\Administrator\Service\SchemaValidator;
use Joomla\Component\Jmcp\Administrator\Service\ToolRegistry;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Psr\Log\LoggerInterface;

return new class implements ServiceProviderInterface {

    public function register(Container $container): void
    {
        // Register MVC and Router factories for our namespace
        $container->registerServiceProvider(new MVCFactoryProvider('\\Joomla\\Component\\Jmcp'));
        $container->registerServiceProvider(new RouterFactoryProvider('\\Joomla\\Component\\Jmcp'));

        $container->set(Registry::class, new Registry());

        // Custom Logger wrapper around Joomla's native Log class
        $container->share(LoggerInterface::class, function () {
            return new class implements LoggerInterface {
                public function emergency($message, array $context = []): void { $this->log('emergency', $message, $context); }
                public function alert($message, array $context = []): void { $this->log('alert', $message, $context); }
                public function critical($message, array $context = []): void { $this->log('critical', $message, $context); }
                public function error($message, array $context = []): void { $this->log('error', $message, $context); }
                public function warning($message, array $context = []): void { $this->log('warning', $message, $context); }
                public function notice($message, array $context = []): void { $this->log('notice', $message, $context); }
                public function info($message, array $context = []): void { $this->log('info', $message, $context); }
                public function debug($message, array $context = []): void { $this->log('debug', $message, $context); }
                public function log($level, $message, array $context = []): void {
                    // Map level to Joomla Log constants
                    $jLevel = \Joomla\CMS\Log\Log::INFO;
                    switch (strtolower((string)$level)) {
                        case 'emergency':
                        case 'alert':
                        case 'critical':
                            $jLevel = \Joomla\CMS\Log\Log::EMERGENCY;
                            break;
                        case 'error':
                            $jLevel = \Joomla\CMS\Log\Log::ERROR;
                            break;
                        case 'warning':
                            $jLevel = \Joomla\CMS\Log\Log::WARNING;
                            break;
                        case 'notice':
                            $jLevel = \Joomla\CMS\Log\Log::NOTICE;
                            break;
                        case 'debug':
                            $jLevel = \Joomla\CMS\Log\Log::DEBUG;
                            break;
                    }
                    \Joomla\CMS\Log\Log::add((string)$message, $jLevel, 'jmcp', null, $context);
                }
            };
        });

        // Auth service
        $container->share(AuthService::class, function () {
            return new AuthService(ComponentHelper::getParams('com_jmcp'));
        });

        // Policy service
        $container->share(PolicyService::class, function () {
            return new PolicyService(ComponentHelper::getParams('com_jmcp'));
        });

        // Tool registry
        $container->share(ToolRegistry::class, function () {
            return new ToolRegistry();
        });

        // Schema validator
        $container->share(SchemaValidator::class, function () {
            return new SchemaValidator();
        });

        // Rate limiter
        $container->share(RateLimiter::class, function () {
            $params = ComponentHelper::getParams('com_jmcp');
            $cacheBackend = new JoomlaCache('com_jmcp_ratelimit');
            return new RateLimiter(
                $cacheBackend,
                (int) $params->get('rate_limit_requests', 60),
                (int) $params->get('rate_limit_window', 60)
            );
        });

        // Metrics service
        $container->share(MetricsService::class, function () {
            return new MetricsService(ComponentHelper::getParams('com_jmcp'));
        });

        // Cache service
        $container->share(CacheService::class, function () {
            $params = ComponentHelper::getParams('com_jmcp');
            $cacheBackend = new JoomlaCache('com_jmcp');
            return new CacheService($cacheBackend, (int) $params->get('cache_ttl', 60));
        });

        // RPC service
        $container->share(RpcService::class, function (Container $container) {
            $params = ComponentHelper::getParams('com_jmcp');
            $serverName = (string) $params->get('server_name', 'joomla-mcp-server');

            return new RpcService(
                $container->get(CacheService::class),
                $container->get(PolicyService::class),
                $container->get(LoggerInterface::class),
                $container->get(ToolRegistry::class),
                $container->get(SchemaValidator::class),
                $serverName,
                $params
            );
        });

        // Component dispatcher factory
        $container->set(
            ComponentDispatcherFactoryInterface::class,
            function (Container $container) {
                return new class ($container) implements ComponentDispatcherFactoryInterface {
                    private Container $container;

                    public function __construct(Container $container)
                    {
                        $this->container = $container;
                    }

                    public function createDispatcher(CMSApplicationInterface $application, ?Input $input = null): DispatcherInterface
                    {
                        return new Dispatcher(
                            $application,
                            $input ?? $application->getInput(),
                            $this->container->get(MVCFactoryInterface::class)
                        );
                    }
                };
            }
        );

        // Component instance bootstrapper
        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                return new JmcpComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class),
                    $container->get(Registry::class),
                    $container->get(RouterFactoryInterface::class),
                    $container
                );
            }
        );
    }
};
