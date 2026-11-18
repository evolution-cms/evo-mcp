<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Console\Commands;

use EvolutionCMS\eMCP\Support\ToolContract;

use EvolutionCMS\eMCP\Http\Controllers\McpManagerController;
use EvolutionCMS\eMCP\Servers\ContentServer;
use EvolutionCMS\eMCP\Services\ServerRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs protocol-level smoke checks against any configured web MCP server.
 *
 * The canonical content server additionally verifies the required Evolution
 * toolset, while third-party servers only need to satisfy the MCP contract.
 */
class eMcpTestCommand extends Command
{
    protected $signature = 'emcp:test
        {--server= : Test a specific server handle}
        {--expect-tool=* : Tool name that must be listed by tools/list (repeatable)}';

    protected $description = 'Run Gate A smoke checks for initialize/tools:list contract.';

    /** Guards against a server that never stops returning nextCursor. */
    private const MAX_TOOL_PAGES = 100;

    /**
     * Execute initialize and tools/list against the selected server handle.
     *
     * Registry rejection reasons are rendered when configuration prevents the
     * requested server from being tested.
     */
    public function handle(ServerRegistry $registry): int
    {
        $this->line('Running eMCP Gate A smoke checks...');

        $servers = $registry->allEnabled();
        if ($servers === []) {
            $this->error('No valid enabled servers found in config(mcp.servers).');
            $this->renderRegistryDiagnostics($registry);
            return self::FAILURE;
        }

        $targetHandle = trim((string)$this->option('server'));
        $serverHandle = $targetHandle !== '' ? $targetHandle : $this->firstWebHandle($servers);

        if ($serverHandle === null) {
            $this->error('No enabled web transport server found.');
            return self::FAILURE;
        }

        $serverClass = $registry->resolveWebServerClassByHandle($serverHandle);
        if ($serverClass === null) {
            $this->error("Server [{$serverHandle}] is not a valid web MCP server.");
            $this->renderRegistryDiagnostics($registry);
            return self::FAILURE;
        }

        $this->line("Using server: {$serverHandle} ({$serverClass})");

        $initializePayload = [
            'jsonrpc' => '2.0',
            'id' => 'init-1',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => (object)[],
                'clientInfo' => [
                    'name' => 'emcp-test',
                    'version' => '1.0.0',
                ],
            ],
        ];

        $initialize = $this->dispatchJsonRpc($serverHandle, $initializePayload);
        if (isset($initialize['error'])) {
            $this->error($initialize['error']);
            return self::FAILURE;
        }

        $initializeResponse = $initialize['response'] ?? null;
        if (!$initializeResponse instanceof HttpResponse) {
            $this->error('initialize returned unexpected response type.');
            return self::FAILURE;
        }

        if ($initializeResponse->getStatusCode() !== 200) {
            $this->error('initialize failed with HTTP ' . $initializeResponse->getStatusCode());
            $this->line((string)$initializeResponse->getContent());
            return self::FAILURE;
        }

        $initializeJson = $this->decodeJson((string)$initializeResponse->getContent());
        if ($initializeJson === null) {
            $this->error('initialize returned invalid JSON payload.');
            return self::FAILURE;
        }

        if (!$this->validateInitializeMetadata($initializeJson)) {
            return self::FAILURE;
        }

        $sessionId = trim((string)$initializeResponse->headers->get('MCP-Session-Id', ''));
        if ($sessionId === '') {
            $this->error('initialize response does not contain MCP-Session-Id.');
            return self::FAILURE;
        }

        $this->info('initialize: OK');

        // tools/list is paginated (Laravel MCP sends 15 per page), so follow nextCursor.
        $toolNames = [];
        $cursor = null;
        $page = 0;
        do {
            if (++$page > self::MAX_TOOL_PAGES) {
                $this->error('tools/list kept returning nextCursor after ' . self::MAX_TOOL_PAGES . ' pages.');
                return self::FAILURE;
            }

            $toolsListPayload = [
                'jsonrpc' => '2.0',
                'id' => 'tools-' . $page,
                'method' => 'tools/list',
                'params' => $cursor === null ? (object)[] : ['cursor' => $cursor],
            ];

            $toolsList = $this->dispatchJsonRpc($serverHandle, $toolsListPayload, $sessionId);
            if (isset($toolsList['error'])) {
                $this->error($toolsList['error']);
                return self::FAILURE;
            }

            $toolsListResponse = $toolsList['response'] ?? null;
            if (!$toolsListResponse instanceof HttpResponse) {
                $this->error('tools/list returned unexpected response type.');
                return self::FAILURE;
            }

            if ($toolsListResponse->getStatusCode() !== 200) {
                $this->error('tools/list failed with HTTP ' . $toolsListResponse->getStatusCode());
                $this->line((string)$toolsListResponse->getContent());
                return self::FAILURE;
            }

            $toolsListJson = $this->decodeJson((string)$toolsListResponse->getContent());
            if ($toolsListJson === null) {
                $this->error('tools/list returned invalid JSON payload.');
                return self::FAILURE;
            }

            if (!isset($toolsListJson['result']['tools']) || !is_array($toolsListJson['result']['tools'])) {
                $this->error('tools/list response does not contain result.tools array.');
                $this->line((string)$toolsListResponse->getContent());
                return self::FAILURE;
            }

            $toolNames = array_merge($toolNames, self::toolNamesFromPage($toolsListJson['result']['tools']));
            $cursor = self::nextCursor($toolsListJson['result']);
        } while ($cursor !== null);

        $toolNames = array_values(array_unique($toolNames));

        sort($toolNames);
        $this->line('tools/list returned ' . count($toolNames) . ' tools: ' . implode(', ', $toolNames));

        $requiredTools = $this->requiredToolsForServer($serverClass, (array)$this->option('expect-tool'));

        $missingTools = array_values(array_diff($requiredTools, $toolNames));
        if ($missingTools !== []) {
            $this->error('tools/list does not contain required tools: ' . implode(', ', $missingTools));
            return self::FAILURE;
        }

        $this->info('tools/list: OK');
        $this->info('eMCP smoke test passed.');

        return self::SUCCESS;
    }

    /**
     * Tool names of one tools/list page; entries without a name are skipped.
     *
     * @param  array<int|string, mixed>  $tools
     * @return array<int, string>
     */
    private static function toolNamesFromPage(array $tools): array
    {
        $names = [];
        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                continue;
            }

            $name = trim((string)($tool['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function nextCursor(array $result): ?string
    {
        $cursor = $result['nextCursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * Return package-owned tools that must be present for a canonical server.
     *
     * Third-party servers intentionally receive no built-in requirements so
     * emcp:test remains a protocol smoke test for arbitrary extensions; an
     * Extra names its own tools through --expect-tool.
     *
     * @param  array<int, mixed>  $expected
     * @return array<int, string>
     *
     * @since 1.1.0
     */
    private function requiredToolsForServer(string $serverClass, array $expected = []): array
    {
        $extra = [];
        foreach ($expected as $name) {
            $name = is_string($name) ? trim($name) : '';
            if ($name !== '') {
                $extra[] = $name;
            }
        }

        if ($serverClass !== ContentServer::class) {
            return array_values(array_unique($extra));
        }

        return array_values(array_unique(array_merge([
            'evo.content.search',
            'evo.content.get',
            'evo.content.tree',
            'evo.content.ancestors',
            'evo.content.siblings',
            'evo.model.list',
            'evo.model.get',
            'evo.elements.list',
            'evo.elements.get',
            'evo.write.content.update',
            'evo.write.content.create',
            'evo.write.content.publish',
            'evo.write.elements.save',
            'evo.write.cache.clear',
        ], $extra)));
    }

    /**
     * Print concrete registry rejection reasons below the command failure.
     *
     * @since 1.1.0
     */
    private function renderRegistryDiagnostics(ServerRegistry $registry): void
    {
        foreach ($registry->diagnostics() as $diagnostic) {
            $this->warn('Registry: ' . $diagnostic);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $servers
     */
    private function firstWebHandle(array $servers): ?string
    {
        foreach ($servers as $server) {
            if ((string)($server['transport'] ?? '') !== 'web') {
                continue;
            }

            $handle = trim((string)($server['handle'] ?? ''));
            if ($handle !== '') {
                return $handle;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{response?: HttpResponse, error?: string}
     */
    private function dispatchJsonRpc(string $serverHandle, array $payload, string $sessionId = ''): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return ['error' => 'Failed to encode JSON-RPC payload.'];
        }

        $request = Request::create('/emcp/test', 'POST', [], [], [], [], $json);
        $request->headers->set('Content-Type', 'application/json');
        if ($sessionId !== '') {
            $request->headers->set('MCP-Session-Id', $sessionId);
        }

        try {
            /** @var McpManagerController $controller */
            $controller = app()->make(McpManagerController::class);
            $response = $controller($request, $serverHandle);
        } catch (\Throwable $e) {
            return ['error' => 'JSON-RPC dispatch failed: ' . $e->getMessage()];
        }

        if (!$response instanceof HttpResponse) {
            return ['error' => 'Unexpected response type: ' . get_debug_type($response)];
        }

        if ($response instanceof StreamedResponse) {
            return ['error' => 'Unexpected streamed response in Gate A smoke test.'];
        }

        return ['response' => $response];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $initializeJson
     */
    private function validateInitializeMetadata(array $initializeJson): bool
    {
        $platform = $initializeJson['result']['serverInfo']['platform'] ?? null;
        $platformVersion = $initializeJson['result']['serverInfo']['platformVersion'] ?? null;
        $toolsetVersion = $initializeJson['result']['capabilities']['evo']['toolsetVersion'] ?? null;

        if ($platform !== 'eMCP') {
            $this->error('initialize metadata missing/invalid serverInfo.platform.');
            return false;
        }

        if (!is_string($platformVersion) || trim($platformVersion) === '') {
            $this->error('initialize metadata missing/invalid serverInfo.platformVersion.');
            return false;
        }

        $expectedToolsetVersion = ToolContract::VERSION;
        if ($toolsetVersion !== $expectedToolsetVersion) {
            $this->error(
                'initialize metadata missing/invalid capabilities.evo.toolsetVersion. ' .
                "Expected [{$expectedToolsetVersion}]."
            );
            return false;
        }

        return true;
    }
}
