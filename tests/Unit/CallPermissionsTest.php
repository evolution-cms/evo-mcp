<?php

declare(strict_types=1);

namespace Illuminate\Support {
    if (!class_exists(Str::class, false)) {
        final class Str
        {
            public static function is(string $pattern, string $value): bool
            {
                return $pattern === '*' || $pattern === $value;
            }
        }
    }
}

namespace Illuminate\Http {
    if (!class_exists(AttributeBag::class, false)) {
        final class AttributeBag
        {
            /** @var array<string, mixed> */
            private array $items = [];

            public function set(string $key, mixed $value): void
            {
                $this->items[$key] = $value;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->items[$key] ?? $default;
            }
        }
    }

    if (!class_exists(Request::class, false)) {
        class Request
        {
            public AttributeBag $attributes;

            public function __construct()
            {
                $this->attributes = new AttributeBag();
            }
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Support\CallPermissions;
    use EvolutionCMS\eMCP\Support\SiteDomains;
    use Illuminate\Http\Request;

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /** @var array<string, mixed> $config */
    $config = [];

    if (!function_exists('config')) {
        function config(string $key, mixed $default = null): mixed
        {
            global $config;

            return $config[$key] ?? $default;
        }
    }

    require_once __DIR__ . '/../../src/Support/JsonRpcPayload.php';
    require_once __DIR__ . '/../../src/Services/ScopePolicy.php';
    require_once __DIR__ . '/../../src/Support/ManagerUrl.php';
    require_once __DIR__ . '/../../src/Support/CallPermissions.php';
    require_once __DIR__ . '/../../src/Support/SiteDomains.php';

    $withScopes = static function (array $scopes): Request {
        $request = new Request();
        $request->attributes->set('emcp.auth.scopes', $scopes);

        return $request;
    };

    // Destructive tools off: never, whatever the token carries.
    assertTrue(!CallPermissions::destructiveAllowed($withScopes(['mcp:call', 'mcp:destructive'])), 'destructive off must refuse');

    $config['cms.settings.eMCP.security.enable_destructive_tools'] = true;
    assertTrue(CallPermissions::destructiveAllowed($withScopes(['mcp:call', 'mcp:destructive'])), 'the destructive scope must allow');
    assertTrue(!CallPermissions::destructiveAllowed($withScopes(['mcp:call', 'mcp:write'])), 'a token without the scope must be refused');
    assertTrue(CallPermissions::destructiveAllowed($withScopes(['*'])), 'a wildcard token carries every scope');
    assertTrue(!CallPermissions::destructiveAllowed(null), 'no request (CLI, worker) must be refused');

    $config['cms.settings.eMCP.auth.destructive_scope'] = 'site:destroy';
    assertTrue(!CallPermissions::destructiveAllowed($withScopes(['mcp:destructive'])), 'a renamed scope must be the one checked');
    assertTrue(CallPermissions::destructiveAllowed($withScopes(['site:destroy'])), 'the renamed scope must allow');

    // Scopes not enforced: the settings switch alone decides, as for DestructiveSite tools.
    $config['cms.settings.eMCP.auth.require_scopes'] = false;
    assertTrue(CallPermissions::destructiveAllowed($withScopes([])), 'without enforced scopes the switch decides');

    assertTrue(!CallPermissions::writeAllowed($withScopes(['mcp:write'])), 'write tools off must refuse');
    $config['cms.settings.eMCP.security.enable_write_tools'] = true;
    $config['cms.settings.eMCP.auth.require_scopes'] = true;
    assertTrue(CallPermissions::writeAllowed($withScopes(['mcp:write'])), 'the write scope must allow');

    // Domains: hosts normalised, the request's host reported only when declared.
    assertTrue(SiteDomains::normalise('https://WWW.Site.com:443/x') === 'www.site.com', 'normalise drops scheme, case and default port');
    assertTrue(SiteDomains::normalise('site.com:8080') === 'site.com:8080', 'normalise keeps a non-default port');
    putenv('EVO_SITE_HOSTNAMES=site.com,shop.site.com');
    $described = SiteDomains::describe('Shop.Site.com');
    assertTrue($described['host'] === 'shop.site.com', 'a declared host is reported');
    assertTrue(in_array('site.com', $described['domains'], true), 'the declared domains are listed');
    assertTrue(SiteDomains::describe('evil.example')['host'] === null, 'an undeclared Host header is not taken as the site');

    echo "CallPermissionsTest: OK\n";
}
