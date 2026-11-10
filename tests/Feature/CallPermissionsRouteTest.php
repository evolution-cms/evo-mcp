<?php

declare(strict_types=1);

/**
 * CallPermissions behind the real route middleware: a tool that only sometimes destroys (an
 * overwrite, as in aMount) asks CallPermissions per call. The manager route (`mgr`,
 * emcp.permission) carries no token scopes; the API route (emcp.pat, emcp.impersonate,
 * emcp.permission, emcp.scope) does. EnsureMcpPermission and EnsureMcpScopes run for real;
 * the session cookie, PAT lookup and impersonation are stood in for by what they leave behind.
 */

namespace Symfony\Component\HttpFoundation {
    class Response
    {
        public function __construct(
            protected string $content = '',
            protected int $statusCode = 200
        ) {
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function getContent(): string
        {
            return $this->content;
        }
    }
}

namespace Illuminate\Support {
    final class Str
    {
        public static function contains(string $haystack, string $needle): bool
        {
            return str_contains($haystack, $needle);
        }

        public static function is(string $pattern, string $value): bool
        {
            $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/u';

            return (bool)preg_match($regex, $value);
        }

        public static function uuid(): string
        {
            return '00000000-0000-4000-8000-000000000000';
        }
    }
}

namespace Illuminate\Http {
    use Symfony\Component\HttpFoundation\Response;

    final class HeaderBag
    {
        /** @var array<string, string> */
        private array $items = [];

        public function set(string $name, string $value): void
        {
            $this->items[strtolower($name)] = $value;
        }

        public function get(string $name, mixed $default = null): mixed
        {
            return $this->items[strtolower($name)] ?? $default;
        }
    }

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

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->items);
        }
    }

    class Request
    {
        public HeaderBag $headers;
        public AttributeBag $attributes;

        /** @param array<string, mixed> $routeParameters */
        public function __construct(
            private string $content = '',
            private array $routeParameters = []
        ) {
            $this->headers = new HeaderBag();
            $this->attributes = new AttributeBag();
        }

        public function getContent(): string
        {
            return $this->content;
        }

        public function route(string $key, mixed $default = null): mixed
        {
            return $this->routeParameters[$key] ?? $default;
        }
    }

    class JsonResponse extends Response
    {
        public HeaderBag $headers;

        /** @param array<string, mixed> $payload */
        public function __construct(array $payload, int $status = 200)
        {
            parent::__construct((string)json_encode($payload, JSON_UNESCAPED_SLASHES), $status);
            $this->headers = new HeaderBag();
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Middleware\EnsureMcpPermission;
    use EvolutionCMS\eMCP\Middleware\EnsureMcpScopes;
    use EvolutionCMS\eMCP\Services\ScopePolicy;
    use EvolutionCMS\eMCP\Support\CallPermissions;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Symfony\Component\HttpFoundation\Response;

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            fwrite(STDERR, "[call-permissions-route][FAIL] {$message}\n");
            exit(1);
        }
    }

    /** @var array<string, mixed> $configValues */
    $configValues = [
        'cms.settings.eMCP.auth.mode' => 'pat',
        'cms.settings.eMCP.auth.require_scopes' => true,
        'cms.settings.eMCP.security.enable_destructive_tools' => true,
        'cms.settings.eMCP.acl.permission' => 'emcp',
        'mcp.servers' => [],
    ];

    /** Evo's manager session as core sees it: from a session cookie or from impersonation. */
    $evoSession = ['loggedIn' => false, 'permissions' => []];

    function config(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['configValues'][$key] ?? $default;
    }

    function is_cli(): bool
    {
        return false;
    }

    function evo(): object
    {
        return new class {
            public function isLoggedIn(string $context): bool
            {
                return $context === 'mgr' && $GLOBALS['evoSession']['loggedIn'];
            }

            // int, as Evo's Core::hasPermission() returns
            public function hasPermission(string $permission, string $context): int
            {
                return (int)($context === 'mgr' && in_array($permission, $GLOBALS['evoSession']['permissions'], true));
            }
        };
    }

    function app(): object
    {
        return new class {
            public function bound(string $abstract): bool
            {
                return false;
            }
        };
    }

    require_once __DIR__ . '/../../src/Support/TraceContext.php';
    require_once __DIR__ . '/../../src/Support/TransportError.php';
    require_once __DIR__ . '/../../src/Support/JsonRpcPayload.php';
    require_once __DIR__ . '/../../src/Services/ScopePolicy.php';
    require_once __DIR__ . '/../../src/Middleware/EnsureMcpPermission.php';
    require_once __DIR__ . '/../../src/Middleware/EnsureMcpScopes.php';
    require_once __DIR__ . '/../../src/Support/CallPermissions.php';

    // The tool: replaces an existing file only when CallPermissions allows it.
    $tool = static fn(Request $request): Response => new JsonResponse([
        'outcome' => CallPermissions::destructiveAllowed($request) ? 'replaced' : 'kept',
    ]);

    /** @param array<int, callable(Request, Closure): Response> $stack */
    $run = static function (array $stack, Request $request) use ($tool): Response {
        $pipeline = array_reduce(
            array_reverse($stack),
            static fn(Closure $next, callable $stage): Closure => static fn(Request $r): Response => $stage($r, $next),
            $tool
        );

        return $pipeline($request);
    };

    $permission = static fn(Request $r, Closure $next): Response => (new EnsureMcpPermission())->handle($r, $next);
    $scopes = static fn(Request $r, Closure $next): Response => (new EnsureMcpScopes(new ScopePolicy()))->handle($r, $next);

    // What a session cookie for $permissions leaves: a logged-in manager, no token attributes.
    $managerSession = static function (?array $permissions): Closure {
        return static function (Request $r, Closure $next) use ($permissions): Response {
            $GLOBALS['evoSession'] = ['loggedIn' => $permissions !== null, 'permissions' => $permissions ?? []];

            return $next($r);
        };
    };

    // What EnsureApiPat + ImpersonateManagerUser leave for a valid token owned by an `emcp` manager.
    $tokenAuth = static function (array $tokenScopes): Closure {
        return static function (Request $r, Closure $next) use ($tokenScopes): Response {
            $r->attributes->set('emcp.auth.mode', 'pat');
            $r->attributes->set('emcp.auth.user_id', 1);
            $r->attributes->set('emcp.auth.scopes', $tokenScopes);
            $GLOBALS['evoSession'] = ['loggedIn' => true, 'permissions' => ['emcp']];

            return $next($r);
        };
    };

    $call = static fn(): Request => new Request(
        (string)json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'amount.transfer', 'arguments' => []]]),
        ['server' => 'content']
    );

    $outcome = static fn(Response $r): string => (string)(json_decode($r->getContent(), true)['outcome'] ?? 'status:' . $r->getStatusCode());

    // Manager route: ['mgr', 'emcp.permission', ...].
    $managerRoute = static fn(?array $permissions): array => [$managerSession($permissions), $permission];

    assertTrue($outcome($run($managerRoute(['emcp']), $call())) === 'replaced', 'a permitted manager session must be allowed to overwrite');
    assertTrue($outcome($run($managerRoute([]), $call())) === 'status:403', 'a manager without the eMCP permission must be refused at the route');
    assertTrue($outcome($run($managerRoute(null), $call())) === 'status:403', 'a request without a manager session must be refused at the route');

    // CallPermissions alone, as when a tool is reached without the route's permission gate.
    assertTrue($outcome($run([$managerSession([])], $call())) === 'kept', 'a session without the eMCP permission must not overwrite');
    assertTrue($outcome($run([$managerSession(null)], $call())) === 'kept', 'missing authentication must not overwrite');

    $configValues['cms.settings.eMCP.security.enable_destructive_tools'] = false;
    assertTrue($outcome($run($managerRoute(['emcp']), $call())) === 'kept', 'destructive tools off must keep the file for a manager');
    $configValues['cms.settings.eMCP.security.enable_destructive_tools'] = true;

    // API route: ['emcp.pat', 'emcp.impersonate', 'emcp.permission', 'emcp.scope', ...].
    $apiRoute = static fn(array $tokenScopes): array => [$tokenAuth($tokenScopes), $permission, $scopes];

    assertTrue($outcome($run($apiRoute(['mcp:read', 'mcp:call', 'mcp:destructive']), $call())) === 'replaced', 'a token with the destructive scope must be allowed to overwrite');
    assertTrue($outcome($run($apiRoute(['mcp:read', 'mcp:call']), $call())) === 'kept', 'a token without the destructive scope must keep the file despite the impersonated session');
    assertTrue($outcome($run($apiRoute(['mcp:read']), $call())) === 'status:403', 'a token without mcp:call must be refused at the route');

    echo "CallPermissionsRouteTest: OK\n";
}
