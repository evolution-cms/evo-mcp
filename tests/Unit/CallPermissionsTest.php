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

    /** Manager session and site config as Evo would report them for this request. */
    $evoState = ['cli' => false, 'loggedIn' => false, 'permissions' => [], 'config' => []];

    function is_cli(): bool
    {
        return $GLOBALS['evoState']['cli'];
    }

    function evo(): object
    {
        return new class {
            public function isLoggedIn(string $context): bool
            {
                return $context === 'mgr' && $GLOBALS['evoState']['loggedIn'];
            }

            // int, as Evo's Core::hasPermission() returns
            public function hasPermission(string $permission, string $context): int
            {
                return (int)($context === 'mgr' && in_array($permission, $GLOBALS['evoState']['permissions'], true));
            }

            public function getConfig(string $key): mixed
            {
                return $GLOBALS['evoState']['config'][$key] ?? null;
            }
        };
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

    // Manager session (no token attributes): the eMCP permission decides, not token scopes.
    $config['cms.settings.eMCP.auth.require_scopes'] = true;
    $session = new Request();
    assertTrue(!CallPermissions::destructiveAllowed($session), 'no manager session must be refused');
    $evoState['loggedIn'] = true;
    assertTrue(!CallPermissions::destructiveAllowed($session), 'a manager without the eMCP permission must be refused');
    $evoState['permissions'] = ['site:destroy', 'emcp'];
    assertTrue(CallPermissions::destructiveAllowed($session), 'a permitted manager session must be allowed without token scopes');
    assertTrue(CallPermissions::writeAllowed($session), 'a permitted manager session may write when write tools are on');
    $config['cms.settings.eMCP.acl.permission'] = 'emcp_custom';
    assertTrue(!CallPermissions::destructiveAllowed($session), 'the configured acl.permission must be the one checked');
    $config['cms.settings.eMCP.acl.permission'] = 'emcp';
    $evoState['cli'] = true;
    assertTrue(!CallPermissions::destructiveAllowed($session), 'a CLI process is not a verified manager session');
    $evoState['cli'] = false;
    // Token calls run as an impersonated manager: the session must not stand in for the scope.
    assertTrue(!CallPermissions::destructiveAllowed($withScopes(['mcp:call'])), 'an impersonated token call still needs the scope');
    $jwt = new Request();
    $jwt->attributes->set('sapi.jwt.user_id', 1);
    assertTrue(!CallPermissions::destructiveAllowed($jwt), 'a JWT call without scopes must be refused');
    $config['cms.settings.eMCP.security.enable_destructive_tools'] = false;
    assertTrue(!CallPermissions::destructiveAllowed($session), 'destructive off must refuse a manager too');
    $config['cms.settings.eMCP.security.enable_destructive_tools'] = true;
    $evoState = ['cli' => false, 'loggedIn' => false, 'permissions' => [], 'config' => []];

    // Domains: hosts normalised, the request's host reported only when declared.
    assertTrue(SiteDomains::normalise('https://WWW.Site.com:443/x') === 'www.site.com', 'normalise drops scheme, case and default port');
    assertTrue(SiteDomains::normalise('site.com:8080') === 'site.com:8080', 'normalise keeps a non-default port');
    putenv('EVO_SITE_HOSTNAMES=site.com,shop.site.com');
    $described = SiteDomains::describe('Shop.Site.com');
    assertTrue($described['host'] === 'shop.site.com', 'a declared host is reported');
    assertTrue(in_array('site.com', $described['domains'], true), 'the declared domains are listed');
    assertTrue(SiteDomains::describe('evil.example')['host'] === null, 'an undeclared Host header is not taken as the site');

    // site_url derived from the Host header (Evo's default when it is not pinned) is not a declared domain.
    $evoState['config']['site_url'] = 'http://evil.example/';
    assertTrue(!in_array('evil.example', SiteDomains::all(), true), 'a Host-derived site_url must not become a known domain');
    $described = SiteDomains::describe('evil.example');
    assertTrue($described['host'] === null, 'a Host-derived site_url must not vouch for the request host');
    assertTrue($described['url'] === null, 'a base URL on an undeclared host is not reported');
    $evoState['config']['site_url'] = 'https://shop.site.com/';
    assertTrue(SiteDomains::describe('shop.site.com')['url'] === 'https://shop.site.com/', 'a base URL on a declared host is reported');

    // Nothing declared: nothing to check against, so the request host is reported as seen.
    putenv('EVO_SITE_HOSTNAMES=');
    $evoState['config']['site_url'] = 'http://evil.example/';
    assertTrue(SiteDomains::all() === [], 'the base URL alone declares no domain');
    assertTrue(SiteDomains::describe('evil.example')['host'] === 'evil.example', 'with nothing declared the request host is reported');

    echo "CallPermissionsTest: OK\n";
}
