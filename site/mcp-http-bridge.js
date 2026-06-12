#!/usr/bin/env node

/**
 * JMCP HTTP-to-stdio Bridge
 * Copyright (C) 2026 JMCP Team
 * Licensed under the GNU General Public License version 2 or later.
 *
 * Bridges MCP clients using stdio transport to the remote JMCP HTTP endpoint.
 * Usage: node mcp-http-bridge.js <endpoint-url> [bearer-token]
 */

const https = require('https');
const http = require('http');
const { URL } = require('url');

const endpoint = process.argv[2];
const bearerToken = process.argv[3] || process.env.JMCP_BEARER_TOKEN || process.env.HTTP_AUTH_BEARER || '';
const rejectUnauthorized = process.env.MCP_IGNORE_SSL !== '1';

function log(message, data = null) {
    const timestamp = new Date().toISOString().replace('T', ' ').replace('Z', '');
    const dataStr = data ? ` ${JSON.stringify(data)}` : '';
    process.stderr.write(`${timestamp} [jmcp] ${message}${dataStr}\n`);
}

if (!endpoint) {
    console.error('Error: Endpoint URL is required');
    console.error('Usage: node mcp-http-bridge.js <endpoint-url> [bearer-token]');
    process.exit(1);
}

log(`Bridging stdio to ${endpoint}`);

function sendRequest(id, method, params = {}) {
    return new Promise((resolve, reject) => {
        const url = new URL(endpoint);
        const isHttps = url.protocol === 'https:';
        const client = isHttps ? https : http;

        const payload = JSON.stringify({
            jsonrpc: '2.0',
            id,
            method,
            params,
        });

        const options = {
            hostname: url.hostname,
            port: url.port || (isHttps ? 443 : 80),
            path: url.pathname + url.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(payload),
            },
            rejectUnauthorized,
        };

        if (bearerToken) {
            options.headers.Authorization = `Bearer ${bearerToken}`;
            // WAMP/Apache often strips Authorization; JMCP accepts this fallback.
            options.headers['X-JMCP-Token'] = bearerToken;
        }

        const req = client.request(options, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => {
                if (res.statusCode === 204) {
                    resolve(null);
                    return;
                }
                if (res.statusCode >= 400 && !data.trim().startsWith('{')) {
                    reject(new Error(`HTTP ${res.statusCode}: ${data.slice(0, 200)}`));
                    return;
                }
                try {
                    const response = JSON.parse(data);
                    if (response && response.id === null && id !== undefined && id !== null) {
                        response.id = id;
                    }
                    resolve(response);
                } catch (error) {
                    reject(new Error(`Invalid JSON (HTTP ${res.statusCode}): ${error.message}`));
                }
            });
        });

        req.on('error', reject);
        req.write(payload);
        req.end();
    });
}

function writeOutput(data) {
    process.stdout.write(`${JSON.stringify(data)}\n`);
}

let serverName = 'joomla-mcp-server';

async function handleInput(line) {
    let requestId;
    try {
        const request = JSON.parse(line);
        requestId = request.id !== undefined ? request.id : undefined;
        const { method, params } = request;

        log(`Handling ${method}`, { server: serverName });

        const response = await sendRequest(requestId, method, params || {});

        if (response?.result?.serverInfo) {
            serverName = response.result.serverInfo.name;
        }

        if (method === 'tools/list' && response?.result?.tools) {
            log(`Found ${response.result.tools.length} tools`, { server: serverName });
        }

        if (response !== null) {
            writeOutput(response);
        }
    } catch (error) {
        writeOutput({
            jsonrpc: '2.0',
            id: requestId,
            error: {
                code: -32603,
                message: error.message,
            },
        });
    }
}

let buffer = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
    buffer += chunk;
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';

    for (const line of lines) {
        if (line.trim()) {
            handleInput(line.trim());
        }
    }
});

process.stdin.on('end', () => {
    if (buffer.trim()) {
        handleInput(buffer.trim());
    }
});

process.on('SIGINT', () => process.exit(0));
process.on('SIGTERM', () => process.exit(0));
