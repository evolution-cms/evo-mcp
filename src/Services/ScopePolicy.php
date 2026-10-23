<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\eMCP\Support\JsonRpcPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScopePolicy
{
    /**
     * @var array<string, array<int, string>>
     */
    private const BUILTIN_SCOPE_MAP = [
        'mcp:read' => [
            'initialize',
            'ping',
            'tools/list',
            'resources/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
            'completion/complete',
            'resources/templates/list',
            'notifications/*',
        ],
        'mcp:call' => [
            'tools/call',
        ],
        'mcp:admin' => [
            'admin/*',
        ],
    ];

    public function resolveRequestedMethod(Request $request): ?string
    {
        $cached = trim((string)$request->attributes->get('emcp.jsonrpc.method', ''));
        if ($cached !== '') {
            return $cached;
        }

        $method = JsonRpcPayload::method($request);
        if ($method === null) {
            return null;
        }

        $request->attributes->set('emcp.jsonrpc.method', $method);

        return $method;
    }

    public function resolveRequiredScope(?string $serverHandle, string $method): ?string
    {
        $scopeMap = $this->resolveScopeMap($serverHandle);

        foreach ($scopeMap as $scope => $methods) {
            if ($this->matchesMethod($method, $methods)) {
                return $scope;
            }
        }

        if (str_starts_with($method, 'admin/')) {
            return 'mcp:admin';
        }

        return null;
    }

    public function requestHasScope(Request $request, string $requiredScope): bool
    {
        $scopes = $this->requestScopes($request);
        if (in_array('*', $scopes, true)) {
            return true;
        }

        return in_array($requiredScope, $scopes, true);
    }

    /**
     * Scopes granted to the request by whichever auth middleware ran (PAT first, then sApi JWT).
     *
     * @return array<int, string>
     */
    public function requestScopes(Request $request): array
    {
        $scopes = $request->attributes->get('emcp.auth.scopes');
        if ($scopes === null) {
            $scopes = $request->attributes->get('sapi.jwt.scopes', []);
        }

        if (is_string($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopes))));
        }

        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $value): string => trim((string)$value),
            $scopes
        )));
    }

    /**
     * Scope a `tools/call` needs on top of `mcp:call`, or null when the tool is not a write tool.
     */
    public function resolveToolScope(string $toolName): ?string
    {
        $isWrite = str_starts_with(trim($toolName), 'evo.write.')
            || ($this->registry()?->isWriteTool($toolName) ?? false);

        if (!$isWrite) {
            return null;
        }

        return $this->configuredScope('write_scope', 'mcp:write');
    }

    /**
     * Every scope a `tools/call` needs on top of `mcp:call`: the write scope for write tools,
     * plus the destructive scope for DestructiveSite tools and the code scope for a call that
     * stores backend code (ExecutesCode, or a built-in tool judged from its arguments).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, string>
     */
    public function resolveToolScopes(string $toolName, array $arguments = []): array
    {
        $scopes = [];

        $writeScope = $this->resolveToolScope($toolName);
        if ($writeScope !== null) {
            $scopes[] = $writeScope;
        }

        if ($this->registry()?->isDestructiveTool($toolName) ?? false) {
            $scopes[] = $this->configuredScope('destructive_scope', 'mcp:destructive');
        }

        if ($this->registry()?->writesCode($toolName, $arguments) ?? false) {
            $scopes[] = $this->configuredScope('code_scope', 'mcp:code');
        }

        return array_values(array_unique($scopes));
    }

    private function configuredScope(string $key, string $default): string
    {
        $scope = trim((string)config('cms.settings.eMCP.auth.' . $key, $default));

        return $scope !== '' ? $scope : $default;
    }

    private function registry(): ?ToolRegistry
    {
        if (!function_exists('app') || !app()->bound(ToolRegistry::class)) {
            return null;
        }

        return app(ToolRegistry::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function resolveScopeMap(?string $serverHandle): array
    {
        $serverMap = $this->serverScopeMap($serverHandle);
        if ($serverMap !== []) {
            return $serverMap;
        }

        $globalMap = $this->normalizeScopeMap(config('cms.settings.eMCP.auth.scope_map', []));
        if ($globalMap !== []) {
            return $globalMap;
        }

        return self::BUILTIN_SCOPE_MAP;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function serverScopeMap(?string $serverHandle): array
    {
        $serverHandle = trim((string)$serverHandle);
        if ($serverHandle === '') {
            return [];
        }

        $servers = config('mcp.servers', []);
        if (!is_array($servers)) {
            return [];
        }

        foreach ($servers as $server) {
            if (!is_array($server)) {
                continue;
            }

            if (trim((string)($server['handle'] ?? '')) !== $serverHandle) {
                continue;
            }

            return $this->normalizeScopeMap($server['scope_map'] ?? []);
        }

        return [];
    }

    /**
     * @param  mixed  $map
     * @return array<string, array<int, string>>
     */
    private function normalizeScopeMap(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $scope => $methods) {
            $scope = trim((string)$scope);
            if ($scope === '') {
                continue;
            }

            if (is_string($methods)) {
                $methods = [$methods];
            }

            if (!is_array($methods)) {
                continue;
            }

            $prepared = [];
            foreach ($methods as $method) {
                $method = trim((string)$method);
                if ($method !== '') {
                    $prepared[] = $method;
                }
            }

            if ($prepared !== []) {
                $normalized[$scope] = array_values(array_unique($prepared));
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesMethod(string $method, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (Str::contains($pattern, '*')) {
                if (Str::is($pattern, $method)) {
                    return true;
                }

                continue;
            }

            if ($pattern === $method) {
                return true;
            }
        }

        return false;
    }
}
