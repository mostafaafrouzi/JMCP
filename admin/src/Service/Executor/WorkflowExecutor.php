<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Jmcp\Administrator\Service\ComponentParamsService;
use Joomla\Component\Jmcp\Administrator\Service\PendingChangesService;
use Joomla\Component\Jmcp\Administrator\Service\ToolRegistry;
use Joomla\Component\Jmcp\Administrator\Service\WebhookService;
use Joomla\Registry\Registry;

class WorkflowExecutor
{
    public function createPendingChange(array $params): array
    {
        $service = new PendingChangesService();
        return $service->create(
            (string) ($params['tool_name'] ?? ''),
            (array) ($params['arguments'] ?? []),
            (string) ($params['description'] ?? '')
        );
    }

    public function listPendingChanges(array $params): array
    {
        $service = new PendingChangesService();
        return ['pending' => $service->listPending((int) ($params['limit'] ?? 25))];
    }

    public function approvePendingChange(array $params, ToolRegistry $registry): array
    {
        $id = (int) ($params['id'] ?? 0);
        $service = new PendingChangesService();

        return $service->approve($id, function (string $toolName, array $arguments) use ($registry) {
            return $registry->execute($toolName, $arguments);
        });
    }

    public function rejectPendingChange(array $params): array
    {
        $service = new PendingChangesService();
        return $service->reject((int) ($params['id'] ?? 0), (string) ($params['reason'] ?? ''));
    }

    public function triggerWebhook(array $params): array
    {
        $config = ComponentHelper::getParams('com_jmcp');
        $service = new WebhookService($config);

        return $service->dispatch(
            (string) ($params['event'] ?? 'jmcp.test'),
            (array) ($params['payload'] ?? [])
        );
    }

    public function listWebhookEvents(array $params): array
    {
        $config = ComponentHelper::getParams('com_jmcp');
        $service = new WebhookService($config);
        return ['events' => $service->listEvents((int) ($params['limit'] ?? 25))];
    }

    public function configureWebhook(array $params): array
    {
        $service = new ComponentParamsService();
        $paramsRegistry = $service->get();

        if (isset($params['url'])) {
            $paramsRegistry->set('webhook_url', (string) $params['url']);
        }
        if (isset($params['secret'])) {
            $paramsRegistry->set('webhook_secret', (string) $params['secret']);
        }
        if (isset($params['enabled'])) {
            $paramsRegistry->set('webhook_enabled', (int) ((bool) $params['enabled']));
        }

        $service->save($paramsRegistry);

        return [
            'url'     => (string) $paramsRegistry->get('webhook_url', ''),
            'enabled' => (bool) $paramsRegistry->get('webhook_enabled', 0),
            'message' => 'Webhook configuration saved.',
        ];
    }

    public function getWebhookConfig(array $params): array
    {
        $config = ComponentHelper::getParams('com_jmcp');

        return [
            'url'     => (string) $config->get('webhook_url', ''),
            'enabled' => (bool) $config->get('webhook_enabled', 0),
            'has_secret' => (string) $config->get('webhook_secret', '') !== '',
        ];
    }
}
