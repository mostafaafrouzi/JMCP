<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class WebhookService
{
    private Registry $params;

    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    public function dispatch(string $event, array $payload): array
    {
        $url = trim((string) $this->params->get('webhook_url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['sent' => false, 'message' => 'Webhook URL not configured.'];
        }

        $body = json_encode([
            'event'     => $event,
            'timestamp' => Factory::getDate('now', 'UTC')->toSql(true),
            'site'      => \Joomla\CMS\Uri\Uri::root(),
            'payload'   => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        if ($ch === false) {
            return ['sent' => false, 'message' => 'Failed to init curl.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-JMCP-Event: ' . $event],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $secret = (string) $this->params->get('webhook_secret', '');
        if ($secret !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-JMCP-Event: ' . $event,
                'X-JMCP-Signature: ' . hash_hmac('sha256', $body, $secret),
            ]);
        }

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logEvent($event, $code, $payload);

        return ['sent' => $code >= 200 && $code < 300, 'http_code' => $code, 'response' => $response];
    }

    private function logEvent(string $event, int $code, array $payload): void
    {
        try {
            $row = new \stdClass();
            $row->created  = Factory::getDate()->toSql();
            $row->event    = substr($event, 0, 64);
            $row->http_code = $code;
            $row->payload  = json_encode($payload, JSON_UNESCAPED_UNICODE);
            Factory::getDbo()->insertObject('#__jmcp_webhook_log', $row);
        } catch (\Throwable $e) {
        }
    }

    /** @return array<int, object> */
    public function listEvents(int $limit = 25): array
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jmcp_webhook_log'))
                ->order($db->quoteName('id') . ' DESC');
            return $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
