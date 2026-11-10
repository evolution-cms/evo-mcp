<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use EvolutionCMS\eMCP\Services\ScopePolicy;
use Illuminate\Http\Request;
use Throwable;

/**
 * What a contributed tool may do in *this* call, beyond what its markers already gate.
 *
 * A tool is marked DestructiveSite when everything it does destroys data, and eMCP then hides
 * and refuses it unless destructive tools are on and the caller may use them. Many tools only
 * *sometimes* destroy: a file transfer that may replace an existing file, a sync that may
 * overwrite an older copy. Marking them destructive would hide the harmless case; not marking
 * them would let the harmful one through. Such a tool asks here instead, per call, and narrows
 * its own behaviour (aMount keeps an existing file and says so):
 *
 *     if (CallPermissions::destructiveAllowed()) { ... replace ... } else { ... keep, report ... }
 *
 * The answer follows the same rules as the gate for DestructiveSite tools on each route:
 * `security.enable_destructive_tools` on, and then
 * - token calls (PAT or sApi JWT): the token carries `auth.destructive_scope` (`mcp:destructive`),
 *   unless scopes are not enforced (`auth.mode = none`, `auth.require_scopes = false`);
 * - manager-session calls (no token): a logged-in manager with the eMCP permission
 *   (`acl.permission`), as the manager route requires;
 * - anything else (CLI, worker, no request): refused.
 * Call it while handling a tool call: it reads that call's request.
 */
final class CallPermissions
{
    /** Request attributes left only by token authentication (EnsureApiPat, sApi JWT, ImpersonateManagerUser). */
    private const TOKEN_ATTRIBUTES = [
        'emcp.auth.mode',
        'emcp.auth.user_id',
        'emcp.auth.scopes',
        'sapi.jwt.payload',
        'sapi.jwt.user_id',
        'sapi.jwt.sub',
        'sapi.jwt.scopes',
    ];

    public static function destructiveAllowed(?Request $request = null): bool
    {
        if (!(bool)config('cms.settings.eMCP.security.enable_destructive_tools', false)) {
            return false;
        }

        return self::granted($request, 'destructive_scope', 'mcp:destructive');
    }

    public static function writeAllowed(?Request $request = null): bool
    {
        if (!(bool)config('cms.settings.eMCP.security.enable_write_tools', false)) {
            return false;
        }

        return self::granted($request, 'write_scope', 'mcp:write');
    }

    private static function granted(?Request $request, string $key, string $default): bool
    {
        $request ??= self::currentRequest();
        if ($request === null) {
            return false;
        }

        // Checked first: token calls also run inside an impersonated manager session.
        if (self::isTokenAuthenticated($request)) {
            return self::scopeGranted($request, $key, $default);
        }

        return self::isPermittedManagerSession();
    }

    private static function isTokenAuthenticated(Request $request): bool
    {
        foreach (self::TOKEN_ATTRIBUTES as $attribute) {
            if ($request->attributes->get($attribute) !== null) {
                return true;
            }
        }

        return false;
    }

    private static function scopeGranted(Request $request, string $key, string $default): bool
    {
        $mode = strtolower(trim((string)config('cms.settings.eMCP.auth.mode', 'sapi_jwt')));
        if ($mode === 'none' || !(bool)config('cms.settings.eMCP.auth.require_scopes', true)) {
            return true;
        }

        $scope = trim((string)config('cms.settings.eMCP.auth.' . $key, $default));

        return (new ScopePolicy())->requestHasScope($request, $scope !== '' ? $scope : $default);
    }

    private static function isPermittedManagerSession(): bool
    {
        $cli = function_exists('is_cli') ? (bool)is_cli() : PHP_SAPI === 'cli';
        if ($cli || !function_exists('evo')) {
            return false;
        }

        try {
            if (!evo()->isLoggedIn('mgr')) {
                return false;
            }

            $permission = trim((string)config('cms.settings.eMCP.acl.permission', 'emcp'));

            // Evo answers with int 0/1.
            return (bool)evo()->hasPermission($permission !== '' ? $permission : 'emcp', 'mgr');
        } catch (Throwable) {
            return false;
        }
    }

    private static function currentRequest(): ?Request
    {
        try {
            if (function_exists('app') && app()->bound('request')) {
                $request = app('request');

                return $request instanceof Request ? $request : null;
            }
        } catch (Throwable) {
        }

        return null;
    }
}
