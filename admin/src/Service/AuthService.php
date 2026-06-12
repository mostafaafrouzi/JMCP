<?php

/**
 * @package     JMCP - Joomla MCP Server
 * @copyright   Copyright (C) 2026 JMCP Team. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class AuthService
{
    private Registry $config;

    public function __construct(Registry $config)
    {
        $this->config = $config;
    }

    public function authenticate(): ?array
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $mcpToken = $this->config->get('mcp_bearer_token', '');
        $ipAllowList = array_filter(array_map('trim', explode(',', (string) $this->config->get('ip_allow_list', ''))));
        $requireAuth = (bool) $this->config->get('require_auth', true);

        // IP Whitelist check
        if (!empty($ipAllowList)) {
            $remoteIp = $this->getClientIp();
            if (!in_array($remoteIp, $ipAllowList, true)) {
                return ['error' => 'IP address not authorized.', 'code' => JsonRpc::FORBIDDEN];
            }
        }

        // Bearer Token check
        if ($requireAuth) {
            if (empty($mcpToken)) {
                return ['error' => 'Authentication is required but no Bearer token is configured.', 'code' => JsonRpc::FORBIDDEN];
            }

            $authHeader = $this->resolveAuthorizationHeader($input);
            $providedToken = $this->extractBearerToken($authHeader);

            // Fallback when Apache strips Authorization (common on WAMP/XAMPP)
            if ($providedToken === '') {
                $providedToken = trim((string) $input->server->getString('HTTP_X_JMCP_TOKEN', ''));
            }

            if (empty($providedToken)) {
                return ['error' => 'Missing bearer token in Authorization header.', 'code' => JsonRpc::UNAUTHORIZED];
            }

            if (!hash_equals($mcpToken, $providedToken)) {
                return ['error' => 'Invalid token.', 'code' => JsonRpc::UNAUTHORIZED];
            }
        }

        return null;
    }

    private function getClientIp(): string
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $remoteAddr = $input->server->getString('REMOTE_ADDR', '');

        // Trust proxies if configured
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) $this->config->get('trusted_proxies', ''))));

        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $input->server->getString('HTTP_X_FORWARDED_FOR', '');
            if (!empty($forwarded)) {
                $ips = array_map('trim', explode(',', $forwarded));
                return $ips[0];
            }
        }

        return $remoteAddr;
    }

    private function resolveAuthorizationHeader($input): string
    {
        $authHeader = $input->server->getString('HTTP_AUTHORIZATION', '');
        if ($authHeader !== '') {
            return $authHeader;
        }

        $authHeader = $input->server->getString('REDIRECT_HTTP_AUTHORIZATION', '');
        if ($authHeader !== '') {
            return $authHeader;
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        return (string) $value;
                    }
                }
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        return (string) $value;
                    }
                }
            }
        }

        return '';
    }

    private function extractBearerToken(string $authHeader): string
    {
        if ($authHeader === '') {
            return '';
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return '';
    }
}
