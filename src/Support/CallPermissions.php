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
 * and refuses it unless destructive tools are on and the token carries the destructive scope.
 * Many tools only *sometimes* destroy: a file transfer that may replace an existing file, a
 * sync that may overwrite an older copy. Marking them destructive would hide the harmless
 * case; not marking them would let the harmful one through. Such a tool asks here instead,
 * per call, and narrows its own behaviour (aMount keeps an existing file and says so):
 *
 *     if (CallPermissions::destructiveAllowed()) { ... replace ... } else { ... keep, report ... }
 *
 * The answer follows the same rules as the gate for DestructiveSite tools:
 * `security.enable_destructive_tools` on, and - unless scopes are not enforced (`auth.mode =
 * none`, `auth.require_scopes = false`) - the request's token carrying `auth.destructive_scope`
 * (`mcp:destructive`). Call it while handling a tool call: it reads that call's request.
 */
final class CallPermissions
{
    public static function destructiveAllowed(?Request $request = null): bool
    {
        if (!(bool)config('cms.settings.eMCP.security.enable_destructive_tools', false)) {
            return false;
        }

        return self::scopeGranted($request, 'destructive_scope', 'mcp:destructive');
    }

    public static function writeAllowed(?Request $request = null): bool
    {
        if (!(bool)config('cms.settings.eMCP.security.enable_write_tools', false)) {
            return false;
        }

        return self::scopeGranted($request, 'write_scope', 'mcp:write');
    }

    private static function scopeGranted(?Request $request, string $key, string $default): bool
    {
        $request ??= self::currentRequest();
        if ($request === null) {
            return false;
        }

        $mode = strtolower(trim((string)config('cms.settings.eMCP.auth.mode', 'sapi_jwt')));
        if ($mode === 'none' || !(bool)config('cms.settings.eMCP.auth.require_scopes', true)) {
            return true;
        }

        $scope = trim((string)config('cms.settings.eMCP.auth.' . $key, $default));

        return (new ScopePolicy())->requestHasScope($request, $scope !== '' ? $scope : $default);
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
