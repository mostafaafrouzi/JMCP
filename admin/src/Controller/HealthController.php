<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Version;
use Joomla\Component\Jmcp\Administrator\Service\DatabaseInstaller;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;
use Joomla\Component\Jmcp\Administrator\Service\Tier\LicenseService;

class HealthController extends BaseController
{
    public function check(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json', true);

        $version = new Version();
        $license = new LicenseService(ComponentHelper::getParams('com_jmcp'));
        $tables  = new DatabaseInstaller();

        $dbOk = true;
        try {
            $tables->ensureTables();
            $db = Factory::getDbo();
            $db->setQuery('SELECT 1')->loadResult();
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        echo json_encode([
            'status'         => $dbOk ? 'ok' : 'degraded',
            'joomla_version' => $version->getShortVersion(),
            'jmcp_version'   => $this->getJmcpVersion(),
            'php_version'    => PHP_VERSION,
            'mcp_enabled'    => (bool) ComponentHelper::getParams('com_jmcp')->get('mcp_enabled', 1),
            'license'        => $license->getStatus(),
            'integrations'   => (new IntegrationDetector())->getInstalledList(),
            'timestamp'      => Factory::getDate('now', 'UTC')->toSql(true),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $app->close();
    }

    private function getJmcpVersion(): string
    {
        $manifest = JPATH_ADMINISTRATOR . '/components/com_jmcp/com_jmcp.xml';
        if (!is_file($manifest)) {
            $manifest = JPATH_ADMINISTRATOR . '/components/com_jmcp/jmcp.xml';
        }

        if (is_file($manifest)) {
            $xml = simplexml_load_file($manifest);
            if ($xml && isset($xml->version)) {
                return (string) $xml->version;
            }
        }

        return 'unknown';
    }
}
