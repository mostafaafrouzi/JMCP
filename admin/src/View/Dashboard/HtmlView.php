<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Jmcp\Administrator\Service\AbilityHubService;
use Joomla\Component\Jmcp\Administrator\Service\AuditService;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;
use Joomla\Component\Jmcp\Administrator\Service\MetricsService;
use Joomla\Component\Jmcp\Administrator\Service\PendingChangesService;
use Joomla\Component\Jmcp\Administrator\Service\PolicyService;
use Joomla\Component\Jmcp\Administrator\Service\Tier\FeatureGate;
use Joomla\Component\Jmcp\Administrator\Service\ToolDefinitions;

class HtmlView extends BaseHtmlView
{
    public array $summary = [];
    public array $topTools = [];
    public array $recentRequests = [];
    public array $auditLog = [];
    public array $pendingChanges = [];
    public array $integrations = [];
    public array $toolPolicies = [];
    public array $licenseStatus = [];
    public bool $metricsEnabled = true;
    public bool $productionWarning = false;
    public bool $isPro = false;
    public int $totalTools = 0;
    public string $sseUrl = '';
    public string $healthUrl = '';
    public string $bearerToken = '';
    public string $serverName = 'joomla-mcp-server';
    public string $claudeConfig = '';
    public string $cursorConfig = '';
    public string $bridgeCommand = '';
    public string $abilitiesSaveUrl = '';

    public function display($tpl = null): void
    {
        $params = ComponentHelper::getParams('com_jmcp');
        $policy = new PolicyService($params);
        $gate   = $policy->getFeatureGate();

        $metrics = new MetricsService($params);

        $this->metricsEnabled     = $metrics->isEnabled();
        $this->summary            = $metrics->getSummary();
        $this->topTools           = $metrics->getTopTools();
        $this->recentRequests     = $metrics->getRecentRequests();
        $this->auditLog           = (new AuditService())->getRecent(30);
        $this->pendingChanges     = (new PendingChangesService())->listPending(25);
        $this->bearerToken        = (string) $params->get('mcp_bearer_token', '');
        $this->serverName         = (string) $params->get('server_name', 'joomla-mcp-server');
        $this->integrations       = (new IntegrationDetector())->getInstalledList();
        $this->toolPolicies       = (new AbilityHubService($params))->getEnrichedPolicies(ToolDefinitions::getAll(), $gate);
        $this->totalTools         = count(ToolDefinitions::getAll());
        $this->licenseStatus      = $gate->getLicenseService()->getStatus();
        $this->isPro              = $this->licenseStatus['is_pro'] ?? false;
        $this->productionWarning  = $policy->isProductionWarningRequired();

        $root = rtrim(Uri::root(), '/');
        $this->sseUrl    = $root . '/index.php?option=com_jmcp&task=rpc.handle';
        $this->healthUrl = $root . '/index.php?option=com_jmcp&task=health.check';
        $tokenPlaceholder = $this->bearerToken !== '' ? $this->bearerToken : 'YOUR_JMCP_TOKEN';
        $bridgePath = $root . '/components/com_jmcp/mcp-http-bridge.js';
        $this->bridgeCommand = 'node "' . $bridgePath . '" "' . $this->sseUrl . '" "' . $tokenPlaceholder . '"';
        $this->abilitiesSaveUrl = Route::_('index.php?option=com_jmcp&task=abilities.save');

        $this->claudeConfig = json_encode([
            'mcpServers' => [$this->serverName => ['command' => 'node', 'args' => [$bridgePath, $this->sseUrl, $tokenPlaceholder]]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->cursorConfig = json_encode([
            'mcpServers' => [$this->serverName => ['url' => $this->sseUrl, 'headers' => ['Authorization' => 'Bearer ' . $tokenPlaceholder]]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        HTMLHelper::_('formbehavior.chosen', 'select');
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('COM_JMCP_DASHBOARD_TITLE', 'plug');
        ToolbarHelper::preferences('com_jmcp');
    }
}
