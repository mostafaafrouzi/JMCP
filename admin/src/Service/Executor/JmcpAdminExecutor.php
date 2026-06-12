<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Jmcp\Administrator\Service\AuditService;
use Joomla\Component\Jmcp\Administrator\Service\MetricsService;
use Joomla\Registry\Registry;

class JmcpAdminExecutor
{
    public function listAuditLog(array $params): array
    {
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $audit = new AuditService();

        return ['entries' => $audit->getRecent($limit), 'limit' => $limit];
    }

    public function getMcpMetrics(array $params): array
    {
        $paramsObj = ComponentHelper::getParams('com_jmcp');
        $metrics = new MetricsService($paramsObj);

        return [
            'metrics_enabled' => $metrics->isEnabled(),
            'summary'         => $metrics->getSummary(),
            'top_tools'       => $metrics->getTopTools(),
            'recent_requests' => $metrics->getRecentRequests((int) ($params['limit'] ?? 20)),
        ];
    }
}
