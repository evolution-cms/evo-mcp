<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "[transport][FAIL] {$message}\n");
    exit(1);
}

function info(string $message): void
{
    fwrite(STDOUT, "[transport] {$message}\n");
}

/**
 * @param array<int,string> $headers
 * @return array{status:int,headers:array<string,string>,body:string}
 */
function httpRequest(string $method, string $url, string $body = '', array $headers = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            fail('curl_init failed');
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed === '' || !str_contains($trimmed, ':')) {
                    return strlen($line);
                }
                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);

                return strlen($line);
            },
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        if (!is_string($rawBody)) {
            $error = curl_error($ch);
            curl_close($ch);
            fail('HTTP request failed: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $rawBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
        ],
    ]);

    $rawBody = @file_get_contents($url, false, $context);
    if (!is_string($rawBody)) {
        fail('HTTP request failed using stream wrapper');
    }

    $status = 0;
    $responseHeaders = [];
    /** @var array<int,string> $http_response_header */
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
                $status = (int)$m[1];
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
        }
    }

    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $rawBody];
}

function decodeJsonObject(string $body, string $context): array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        fail("{$context}: response body is not a JSON object");
    }

    return $decoded;
}

function checkToolCatalog(string $url, string $token, string $session, string $fixture, string $metadata): void
{
    // Schema and revision of the fixture tool, read from the same storage directory.
    $schema = dirname($fixture) . '/emcp-test-catalog-schema.json';
    $setSchema = static function (string $type, string $revision) use ($schema): void {
        if (file_put_contents($schema, json_encode(['type' => $type, 'revision' => $revision], JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Could not change the package tool schema');
        }
    };

    $original = file_get_contents($fixture);
    $timestamp = filemtime($metadata);
    if ($original === false || $timestamp === false) {
        fail('Catalog test requires a disposable fixture and Composer metadata');
    }

    $send = static function (string $method, int $id, string $accept, array $params = []) use ($url, $token, $session): array {
        return httpRequest('POST', $url, json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => (object)$params,
        ], JSON_THROW_ON_ERROR), [
            'Content-Type: application/json',
            'Accept: ' . $accept,
            'Authorization: Bearer ' . $token,
            'MCP-Session-Id: ' . $session,
            'MCP-Protocol-Version: 2025-11-25',
        ]);
    };

    $assertCatalog = static function (array $response, ?string $description): void {
        $payload = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
        if ($response['status'] !== 200 || !is_array($payload['result']['tools'] ?? null)) {
            throw new RuntimeException('Expected a successful tools/list reply');
        }
        $descriptions = array_column($payload['result']['tools'] ?? [], 'description', 'name');
        if (($descriptions['fixture.catalog_revision'] ?? null) !== $description) {
            throw new RuntimeException('Catalog fixture description is missing or stale');
        }
    };
    $readEvents = static function (array $response, int $id): array {
        $events = explode("\n\n", trim($response['body']));
        if ($response['status'] !== 200 || !str_starts_with($response['headers']['content-type'] ?? '', 'text/event-stream')
            || count($events) !== 2 || !str_starts_with($events[0], 'data: ') || !str_starts_with($events[1], 'data: ')) {
            throw new RuntimeException('Expected two events on a short SSE response');
        }
        $notice = json_decode(substr($events[0], 6), true, flags: JSON_THROW_ON_ERROR);
        $reply = json_decode(substr($events[1], 6), true, flags: JSON_THROW_ON_ERROR);
        if (($notice['method'] ?? null) !== 'notifications/tools/list_changed' || array_key_exists('id', $notice)
            || ($reply['id'] ?? null) !== $id) {
            throw new RuntimeException('SSE did not preserve the notification and request ID');
        }

        return $reply;
    };
    $assertJson = static function (array $response, int $id): void {
        if ($response['status'] !== 200 || !str_starts_with($response['headers']['content-type'] ?? '', 'application/json')) {
            throw new RuntimeException('Expected JSON response to tool call ' . $id);
        }
        $payload = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
        if (($payload['id'] ?? null) !== $id) {
            throw new RuntimeException('JSON response lost the request ID');
        }
    };

    $assertSchema = static function (array $response, string $type): void {
        $payload = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
        $schemas = array_column($payload['result']['tools'] ?? [], 'inputSchema', 'name');
        if (($schemas['fixture.catalog_revision']['properties']['value']['type'] ?? null) !== $type) {
            throw new RuntimeException('Catalog fixture schema is missing or stale');
        }
    };

    $accept = 'application/json, text/event-stream';
    $tool = ['name' => 'fixture.catalog_revision'];
    try {
        $setSchema('string', '1');
        $assertCatalog($send('tools/list', 1, $accept, ['per_page' => 50]), trim($original));

        if (!touch($metadata, $timestamp + 1)) {
            throw new RuntimeException('Could not simulate a Composer metadata update');
        }
        $assertJson($send('tools/call', 2, $accept, $tool), 2);

        if (file_put_contents($fixture, 'Updated package tool') === false || !touch($metadata, $timestamp + 2)) {
            throw new RuntimeException('Could not simulate a package tool update');
        }
        $assertJson($send('tools/call', 3, 'application/json, text/event-stream;q=0', $tool), 3);

        $reply = $readEvents($send('tools/call', 4, $accept, $tool), 4);
        if (($reply['result']['content'][0]['text'] ?? null) !== 'Updated package tool') {
            throw new RuntimeException('SSE did not preserve the tool result');
        }

        $assertJson($send('tools/call', 5, $accept, $tool), 5);
        $assertCatalog($send('tools/list', 6, $accept, ['per_page' => 50]), 'Updated package tool');
        $assertJson($send('tools/call', 7, $accept, $tool), 7);

        // Only the schema changes here: no watched file or tool identity does.
        $setSchema('integer', '1');
        $assertJson($send('tools/call', 10, $accept, $tool), 10);
        $setSchema('integer', '2');
        $readEvents($send('tools/call', 11, $accept, $tool), 11);
        $assertJson($send('tools/call', 12, $accept, $tool), 12);
        $assertSchema($send('tools/list', 13, $accept, ['per_page' => 50]), 'integer');
        $setSchema('integer', '3');
        $assertJson($send('tools/call', 14, $accept, $tool), 14);

        if (file_put_contents($fixture, 'Hidden package tool') === false) {
            throw new RuntimeException('Could not change package tool visibility');
        }
        $readEvents($send('tools/call', 8, $accept, $tool), 8);
        $assertCatalog($send('tools/list', 9, $accept, ['per_page' => 50]), null);
    } finally {
        file_put_contents($fixture, $original);
        touch($metadata, $timestamp);
        @unlink($schema);
    }

    info('Tool catalog checks passed.');
}

function checkCatalogCleanup(string $url, string $token, string $initialize, string $directory): void
{
    $files = static fn (): array => glob($directory . '/*/*/*') ?: [];
    $start = static function () use ($url, $token, $initialize, $files): array {
        $known = $files();
        $response = httpRequest('POST', $url, $initialize, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
        $created = array_values(array_diff($files(), $known));
        if ($response['status'] !== 200 || count($created) !== 1) {
            fail('Initialize must store one catalog record');
        }

        return $created;
    };

    [$abandoned] = $start();
    [$live] = $start();
    if (!touch($abandoned, time() - 100000) || !touch($live, time() - 80000)) {
        fail('Catalog cleanup test requires write access to the state directory');
    }
    $start();
    clearstatcache();
    if (is_file($abandoned)) {
        fail('A new session must remove the stale catalog record');
    }
    if (!is_file($live)) {
        fail('A new session must preserve the live catalog record');
    }

    info('Tool catalog cleanup checks passed.');
}

$enabled = getenv('EMCP_INTEGRATION_ENABLED');
if ($enabled !== '1') {
    info('Skipped (set EMCP_INTEGRATION_ENABLED=1).');
    exit(0);
}

$baseUrl = rtrim(trim((string)getenv('EMCP_BASE_URL')), '/');
$server = trim((string)getenv('EMCP_SERVER_HANDLE'));
if ($baseUrl === '' || $server === '') {
    fail('EMCP_BASE_URL and EMCP_SERVER_HANDLE are required.');
}

$apiPath = trim((string)getenv('EMCP_API_PATH'));
$apiToken = trim((string)getenv('EMCP_API_TOKEN'));
$mgrPath = trim((string)getenv('EMCP_MANAGER_PATH'));
$mgrCookie = trim((string)getenv('EMCP_MANAGER_COOKIE'));
$requireManager = getenv('EMCP_REQUIRE_MANAGER_ENDPOINT') === '1';

$initializePayload = json_encode([
    'jsonrpc' => '2.0',
    'id' => 'transport-init',
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2025-11-25',
        'capabilities' => (object)[],
        'clientInfo' => ['name' => 'transport-integration', 'version' => '1.0.0'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($initializePayload)) {
    fail('Failed to encode initialize payload');
}

$apiRan = false;
if ($apiPath !== '' && $apiToken !== '') {
    $apiRan = true;
    $apiUrl = $baseUrl . str_replace('{server}', $server, $apiPath);

    $apiGet = httpRequest('GET', $apiUrl, '', ['Authorization: Bearer ' . $apiToken]);
    if ($apiGet['status'] !== 405) {
        fail('API GET endpoint must return 405, got ' . $apiGet['status']);
    }

    $apiPost = httpRequest('POST', $apiUrl, $initializePayload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
    ]);
    if ($apiPost['status'] !== 200) {
        fail('API initialize expected 200, got ' . $apiPost['status']);
    }

    $apiJson = decodeJsonObject($apiPost['body'], 'API initialize');
    if (($apiJson['result']['serverInfo']['platform'] ?? null) !== 'eMCP') {
        fail('API initialize missing platform=eMCP');
    }

    $sessionId = trim((string)($apiPost['headers']['mcp-session-id'] ?? ''));
    if ($sessionId === '') {
        fail('API initialize response missing MCP-Session-Id header');
    }

    $catalogState = rtrim((string)getenv('EMCP_CATALOG_STATE_DIR'), '/\\');
    if ($catalogState !== '') {
        checkCatalogCleanup($apiUrl, $apiToken, $initializePayload, $catalogState);
    }

    $catalogFixture = (string)getenv('EMCP_CATALOG_FIXTURE');
    $catalogMetadata = (string)getenv('EMCP_CATALOG_COMPOSER_METADATA');
    if ($catalogFixture !== '' && $catalogMetadata !== '') {
        if (($apiJson['result']['capabilities']['tools']['listChanged'] ?? null) !== true) {
            fail('API initialize must advertise tools.listChanged for catalog checks');
        }
        checkToolCatalog($apiUrl, $apiToken, $sessionId, $catalogFixture, $catalogMetadata);
    }

    info('API endpoint checks passed.');
}

$managerRan = false;
if ($mgrPath !== '' && $mgrCookie !== '') {
    $managerRan = true;
    $managerUrl = $baseUrl . str_replace('{server}', $server, $mgrPath);

    $mgrGet = httpRequest('GET', $managerUrl, '', ['Cookie: ' . $mgrCookie]);
    if ($mgrGet['status'] !== 405) {
        fail('Manager GET endpoint must return 405, got ' . $mgrGet['status']);
    }

    $mgrPost = httpRequest('POST', $managerUrl, $initializePayload, [
        'Content-Type: application/json',
        'Cookie: ' . $mgrCookie,
    ]);
    if ($mgrPost['status'] !== 200) {
        fail('Manager initialize expected 200, got ' . $mgrPost['status']);
    }

    $mgrJson = decodeJsonObject($mgrPost['body'], 'Manager initialize');
    if (($mgrJson['result']['serverInfo']['platform'] ?? null) !== 'eMCP') {
        fail('Manager initialize missing platform=eMCP');
    }

    $mgrSessionId = trim((string)($mgrPost['headers']['mcp-session-id'] ?? ''));
    if ($mgrSessionId === '') {
        fail('Manager initialize response missing MCP-Session-Id header');
    }

    info('Manager endpoint checks passed.');
}

if (!$apiRan && !$managerRan) {
    fail('No endpoints configured. Provide API and/or manager endpoint env vars.');
}

if ($requireManager && !$managerRan) {
    fail('EMCP_REQUIRE_MANAGER_ENDPOINT=1 but manager env variables are missing.');
}

info('Transport endpoint integration checks passed.');
