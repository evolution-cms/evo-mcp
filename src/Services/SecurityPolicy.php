<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\eMCP\Support\BackendCode;
use Illuminate\Support\Str;

final class SecurityPolicy
{
    public function isServerAllowed(string $serverHandle): bool
    {
        $allow = config('cms.settings.eMCP.security.allow_servers', ['*']);
        if (!is_array($allow) || $allow === []) {
            return false;
        }

        $serverHandle = trim($serverHandle);
        if ($serverHandle === '') {
            return false;
        }

        foreach ($allow as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern === '') {
                continue;
            }

            if ($pattern === '*' || Str::is($pattern, $serverHandle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $arguments  tools/call arguments; they tell whether a built-in
     *                                           write tool stores backend code
     */
    public function isToolDenied(string $serverHandle, string $toolName, array $arguments = []): bool
    {
        $toolName = trim($toolName);
        if ($toolName === '') {
            return false;
        }

        if (
            $this->isWriteToolDenied($toolName)
            || $this->isDestructiveToolDenied($toolName)
            || $this->isCodeWriteDenied($toolName, $arguments)
        ) {
            return true;
        }

        return $this->matchesDenyList($serverHandle, $toolName);
    }

    /**
     * Whether `tools/list` should leave the tool out for the current user: it is on a deny list,
     * it is a disabled destructive or code tool, or the user lacks the permission it requires.
     * Disabled evo.write.* / WritesSite tools stay listed (and are refused on call), as before.
     */
    public function isToolHidden(string $serverHandle, string $toolName): bool
    {
        $toolName = trim($toolName);
        if ($toolName === '') {
            return false;
        }

        return $this->matchesDenyList($serverHandle, $toolName)
            || $this->isDestructiveToolDenied($toolName)
            || (($this->registry()?->isCodeTool($toolName) ?? false) && $this->isCodeWriteDenied($toolName))
            || !$this->isToolPermitted($toolName);
    }

    private function matchesDenyList(string $serverHandle, string $toolName): bool
    {
        $global = $this->normalizePatterns(config('cms.settings.eMCP.security.deny_tools', []));
        $server = $this->serverToolDenyPatterns($serverHandle);

        foreach (array_merge($global, $server) as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (Str::is($pattern, $toolName)) {
                return true;
            }
        }

        return false;
    }

    public function isWriteToolDenied(string $toolName): bool
    {
        if ((bool)config('cms.settings.eMCP.security.enable_write_tools', false)) {
            return false;
        }

        return $this->isWriteTool($toolName);
    }

    public function isDestructiveToolDenied(string $toolName): bool
    {
        if ((bool)config('cms.settings.eMCP.security.enable_destructive_tools', false)) {
            return false;
        }

        return $this->registry()?->isDestructiveTool($toolName) ?? false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function isCodeWriteDenied(string $toolName, array $arguments = []): bool
    {
        if ((bool)config('cms.settings.eMCP.security.enable_code_tools', false)) {
            return false;
        }

        return $this->writesCode($toolName, $arguments);
    }

    /**
     * Whether this call stores backend code (ExecutesCode tool, or a built-in write tool whose
     * arguments carry PHP or a code binding).
     *
     * @param  array<string, mixed>  $arguments
     */
    public function writesCode(string $toolName, array $arguments = []): bool
    {
        return $this->registry()?->writesCode(trim($toolName), $arguments) ?? false;
    }

    /**
     * Whether the current manager may make this call: the permission a RequiresPermission tool
     * declares, and for a call that stores backend code one of the permissions that already grant
     * PHP in the manager (BackendCode::permissions()). Other calls always pass here (the tools
     * check permissions themselves). A manager identity is required: no session (auth.mode=none,
     * CLI, a worker without an initiator) means no access. Role 1 passes like it does in the manager.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function isToolPermitted(string $toolName, array $arguments = []): bool
    {
        $toolName = trim($toolName);
        $permissions = $this->registry()?->permissionFor($toolName);
        $code = $this->writesCode($toolName, $arguments);
        if ($permissions === null && !$code) {
            return true;
        }

        if (!function_exists('evo') || !evo()->isLoggedIn('mgr')) {
            return false;
        }

        if ((int)($_SESSION['mgrRole'] ?? 0) === 1) {
            return true;
        }

        if ($code && !$this->hasAnyPermission(BackendCode::permissions())) {
            return false;
        }

        return $permissions === null || $this->hasAnyPermission($permissions);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (evo()->hasPermission($permission, 'mgr')) {
                return true;
            }
        }

        return false;
    }

    /**
     * eMCP's own evo.write.* tools, plus contributed tools marked WritesSite.
     */
    public function isWriteTool(string $toolName): bool
    {
        if (str_starts_with(trim($toolName), 'evo.write.')) {
            return true;
        }

        return $this->registry()?->isWriteTool($toolName) ?? false;
    }

    private function registry(): ?ToolRegistry
    {
        if (!function_exists('app') || !app()->bound(ToolRegistry::class)) {
            return null;
        }

        return app(ToolRegistry::class);
    }

    /**
     * @param  array<string, mixed>  $jsonRpc
     */
    public function resolveToolName(array $jsonRpc): ?string
    {
        if (trim((string)($jsonRpc['method'] ?? '')) !== 'tools/call') {
            return null;
        }

        $params = $jsonRpc['params'] ?? null;
        if (!is_array($params)) {
            return null;
        }

        $name = trim((string)($params['name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<int, mixed>  $patterns
     * @return array<int, string>
     */
    private function normalizePatterns(mixed $patterns): array
    {
        if (!is_array($patterns)) {
            return [];
        }

        $result = [];

        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern !== '') {
                $result[] = $pattern;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<int, string>
     */
    private function serverToolDenyPatterns(string $serverHandle): array
    {
        $servers = config('mcp.servers', []);
        if (!is_array($servers)) {
            return [];
        }

        foreach ($servers as $server) {
            if (!is_array($server)) {
                continue;
            }

            if (trim((string)($server['handle'] ?? '')) !== trim($serverHandle)) {
                continue;
            }

            return $this->normalizePatterns($server['security']['deny_tools'] ?? []);
        }

        return [];
    }
}
