<?php

declare(strict_types=1);

// Minimal stand-ins for the Laravel / Laravel MCP pieces the registry and policies touch.

namespace Illuminate\Support {
    if (!class_exists(Str::class, false)) {
        final class Str
        {
            public static function is(string $pattern, string $value): bool
            {
                if ($pattern === '*') {
                    return true;
                }

                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/u';

                return (bool)preg_match($regex, $value);
            }

            public static function contains(string $haystack, string $needle): bool
            {
                return str_contains($haystack, $needle);
            }

            public static function kebab(string $value): string
            {
                return strtolower((string)preg_replace('/(.)(?=[A-Z])/u', '$1-', $value));
            }
        }
    }
}

namespace Illuminate\Http {
    if (!class_exists(Request::class, false)) {
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
                return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
            }
        }

        final class Request
        {
            public AttributeBag $attributes;

            public int $reads = 0;

            public function __construct(private string $content = '')
            {
                $this->attributes = new AttributeBag();
            }

            public function getContent(): string
            {
                $this->reads++;

                return $this->content;
            }
        }
    }
}

namespace Laravel\Mcp\Server\Attributes {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    final class Name
    {
        public function __construct(public string $value)
        {
        }
    }
}

namespace Laravel\Mcp\Server {
    abstract class Primitive
    {
        protected string $name = '';

        public function name(): string
        {
            return $this->name;
        }
    }

    abstract class Tool extends Primitive
    {
    }
}

namespace Fixtures {
    use EvolutionCMS\eMCP\Contracts\DestructiveSite;
    use EvolutionCMS\eMCP\Contracts\RequiresPermission;
    use EvolutionCMS\eMCP\Contracts\ToolProvider;
    use EvolutionCMS\eMCP\Contracts\WritesSite;
    use Laravel\Mcp\Server\Attributes\Name;
    use Laravel\Mcp\Server\Tool;

    // Contracts must exist before the fixtures below are declared.
    foreach (['WritesSite', 'DestructiveSite', 'ExecutesCode', 'RequiresPermission', 'ToolProvider'] as $contract) {
        require_once dirname(__DIR__, 2) . "/src/Contracts/{$contract}.php";
    }

    #[Name('shop.orders.list')]
    final class ReadTool extends Tool
    {
    }

    #[Name('shop.orders.set_status')]
    final class WriteTool extends Tool implements WritesSite, RequiresPermission
    {
        public static function permission(): string|array
        {
            return 'shop_orders';
        }
    }

    #[Name('shop.orders.delete')]
    final class SoftDeleteTool extends Tool implements DestructiveSite, RequiresPermission
    {
        public static function permission(): string|array
        {
            return ['shop_orders_delete', 'shop_admin'];
        }

        public static function reversible(): bool
        {
            return true;
        }
    }

    #[Name('shop.products.delete')]
    final class HardDeleteTool extends Tool implements DestructiveSite
    {
    }

    #[Name('shop.files.write')]
    final class FileWriteTool extends Tool implements \EvolutionCMS\eMCP\Contracts\ExecutesCode
    {
    }

    #[Name('shop.broken')]
    final class EmptyPermissionTool extends Tool implements RequiresPermission
    {
        public static function permission(): string|array
        {
            return [' '];
        }
    }

    // No #[Name]: Laravel MCP calls it "unnamed-write-tool", and so must the gates.
    final class UnnamedWriteTool extends Tool implements WritesSite
    {
    }

    final class PropertyNamedTool extends Tool implements WritesSite
    {
        protected string $name = 'shop.property_named';
    }

    final class DynamicNameTool extends Tool implements WritesSite
    {
        public function name(): string
        {
            return 'shop.dynamic_' . date('s');
        }
    }

    #[Name('evo.content.get')]
    final class ReservedNameTool extends Tool
    {
    }

    #[Name('shop.orders.list')]
    final class DuplicateNameTool extends Tool implements WritesSite
    {
    }

    #[Name('shop.not_a_tool')]
    final class NotATool
    {
    }

    final class ShopProvider implements ToolProvider
    {
        public function server(): string
        {
            return 'content';
        }

        public function tools(): array
        {
            return [ReadTool::class, WriteTool::class, SoftDeleteTool::class, HardDeleteTool::class, EmptyPermissionTool::class, FileWriteTool::class, 'Fixtures\\Missing'];
        }
    }
}

namespace {
    use EvolutionCMS\eMCP\Services\ScopePolicy;
    use EvolutionCMS\eMCP\Services\SecurityPolicy;
    use EvolutionCMS\eMCP\Services\ToolRegistry;
    use EvolutionCMS\eMCP\Support\JsonRpcPayload;
    use Fixtures\DuplicateNameTool;
    use Fixtures\DynamicNameTool;
    use Fixtures\NotATool;
    use Fixtures\PropertyNamedTool;
    use Fixtures\ReadTool;
    use Fixtures\ReservedNameTool;
    use Fixtures\ShopProvider;
    use Fixtures\UnnamedWriteTool;
    use Fixtures\WriteTool;
    use Illuminate\Http\Request;

    $root = dirname(__DIR__, 2);
    require_once $root . '/src/Support/BackendCode.php';
    require_once $root . '/src/Services/ToolRegistry.php';
    require_once $root . '/src/Services/SecurityPolicy.php';
    require_once $root . '/src/Support/JsonRpcPayload.php';
    require_once $root . '/src/Services/ScopePolicy.php';

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    /** @var array<string, mixed> $config */
    $config = [];

    function config(string $key, mixed $default = null): mixed
    {
        global $config;

        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    final class TestContainer
    {
        /** @var array<string, object> */
        public array $instances = [];

        public function bound(string $id): bool
        {
            return isset($this->instances[$id]);
        }
    }

    $container = new TestContainer();

    function app(?string $id = null): mixed
    {
        global $container;

        return $id === null ? $container : $container->instances[$id];
    }

    final class TestEvo
    {
        public bool $loggedIn = false;

        /** @var array<int, string> */
        public array $permissions = [];

        public function isLoggedIn(string $context): bool
        {
            return $this->loggedIn && $context === 'mgr';
        }

        public function hasPermission(string $permission, string $context): bool
        {
            return $context === 'mgr' && in_array($permission, $this->permissions, true);
        }
    }

    $evo = new TestEvo();

    function evo(): TestEvo
    {
        global $evo;

        return $evo;
    }

    function freshRegistry(): ToolRegistry
    {
        global $container;
        $registry = new ToolRegistry();
        $container->instances[ToolRegistry::class] = $registry;

        return $registry;
    }

    // --- Naming: the registry must see a tool under the name Laravel MCP calls it by -------------

    assertTrue(ToolRegistry::resolveName(ReadTool::class) === 'shop.orders.list', '#[Name] is the tool name');
    assertTrue(ToolRegistry::resolveName(UnnamedWriteTool::class) === 'unnamed-write-tool', 'without #[Name] the name is the kebab-cased class basename');
    assertTrue(ToolRegistry::resolveName(PropertyNamedTool::class) === 'shop.property_named', 'the $name property default counts');
    assertTrue(ToolRegistry::resolveName(DynamicNameTool::class) === null, 'an overridden name() cannot be resolved statically');

    // --- Provider registration --------------------------------------------------------------------

    $registry = freshRegistry();
    $registry->register(new ShopProvider());

    assertTrue(
        $registry->toolsFor('content') === [ReadTool::class, WriteTool::class, Fixtures\SoftDeleteTool::class, Fixtures\HardDeleteTool::class, Fixtures\EmptyPermissionTool::class, Fixtures\FileWriteTool::class],
        'provider tools are kept in order; missing classes are skipped'
    );
    assertTrue($registry->toolsFor('other') === [], 'unknown handle has no tools');
    assertTrue($registry->classFor('shop.orders.list') === ReadTool::class, 'classFor finds a provider tool');
    assertTrue(!$registry->isWriteTool('shop.orders.list'), 'a tool without WritesSite is not a write tool');
    assertTrue($registry->isWriteTool('shop.orders.set_status'), 'WritesSite makes a write tool');
    assertTrue($registry->isWriteTool('shop.orders.delete'), 'DestructiveSite is also a write tool');
    assertTrue($registry->isWriteTool('evo.write.content.update'), 'evo.write.* is always a write tool');
    assertTrue($registry->isDestructiveTool('shop.orders.delete'), 'DestructiveSite is destructive');
    assertTrue(!$registry->isDestructiveTool('shop.orders.set_status'), 'WritesSite alone is not destructive');
    assertTrue($registry->isReversible('shop.orders.delete'), 'reversible() = true is reported');
    assertTrue(!$registry->isReversible('shop.products.delete'), 'without reversible() an operation is irreversible');
    assertTrue($registry->permissionFor('shop.orders.list') === null, 'no RequiresPermission, no required permission');
    assertTrue($registry->permissionFor('shop.orders.set_status') === ['shop_orders'], 'string permission becomes a list');
    assertTrue($registry->permissionFor('shop.orders.delete') === ['shop_orders_delete', 'shop_admin'], 'array permission is any-of');
    assertTrue($registry->permissionFor('shop.broken') === [], 'blank permission declaration denies everyone');
    assertTrue($registry->annotationsFor('shop.orders.delete') === ['readOnlyHint' => false, 'destructiveHint' => true], 'destructive hints');
    assertTrue($registry->annotationsFor('shop.orders.set_status') === ['readOnlyHint' => false], 'a WritesSite tool is not claimed harmless');
    assertTrue($registry->annotationsFor('shop.files.write') === ['readOnlyHint' => false, 'destructiveHint' => true], 'code tools are destructive');
    assertTrue($registry->annotationsFor('evo.write.content.update') === ['readOnlyHint' => false, 'destructiveHint' => true], 'update overwrites');
    assertTrue($registry->annotationsFor('evo.write.elements.save') === ['readOnlyHint' => false, 'destructiveHint' => true], 'elements.save overwrites');
    assertTrue($registry->annotationsFor('evo.write.content.create') === ['readOnlyHint' => false, 'destructiveHint' => false], 'create only adds');
    assertTrue($registry->annotationsFor('evo.write.cache.clear') === ['readOnlyHint' => false, 'destructiveHint' => false], 'cache is rebuilt');
    assertTrue($registry->annotationsFor('shop.orders.list') === [], 'a contributed tool is not claimed read-only');
    assertTrue($registry->annotationsFor('evo.content.get') === ['readOnlyHint' => true], 'built-in read tools are read-only');
    assertTrue($registry->rejected() === [], 'valid provider tools are not rejected');

    // --- extra_tools from config, and fail-closed rejection ---------------------------------------

    $config['mcp.servers'] = [
        ['handle' => 'content', 'extra_tools' => [UnnamedWriteTool::class, DynamicNameTool::class, ReservedNameTool::class, NotATool::class, 'Fixtures\\Missing']],
        ['handle' => 'shop', 'extra_tools' => [PropertyNamedTool::class, DuplicateNameTool::class]],
    ];

    $registry = freshRegistry();
    $registry->add('content', ReadTool::class);

    assertTrue(
        $registry->toolsFor('content') === [ReadTool::class, UnnamedWriteTool::class],
        'only nameable Tool classes outside evo.* are served'
    );
    assertTrue($registry->toolsFor('shop') === [PropertyNamedTool::class], 'a duplicate name is rejected, the first registration wins');
    assertTrue($registry->isWriteTool('unnamed-write-tool'), 'a WritesSite tool without #[Name] is still gated (no fail-open)');
    assertTrue($registry->isWriteTool('shop.property_named'), 'extra_tools tools are gated');
    assertTrue(!$registry->isWriteTool('shop.orders.list'), 'the duplicate WritesSite tool did not take over the name');
    assertTrue($registry->classFor('evo.content.get') === null, 'a reserved name never maps to a contributed class');

    $rejected = $registry->rejected();
    assertTrue(
        array_keys($rejected) === [DynamicNameTool::class, ReservedNameTool::class, NotATool::class, DuplicateNameTool::class],
        'rejected tools are reported: ' . implode(', ', array_keys($rejected))
    );
    assertTrue(str_contains($rejected[DynamicNameTool::class], '#[Name]'), 'the reason tells the author to add #[Name]');

    $config['app.debug'] = true;
    $registry = freshRegistry();
    $threw = false;
    try {
        $registry->toolsFor('content');
    } catch (InvalidArgumentException $e) {
        $threw = str_contains($e->getMessage(), DynamicNameTool::class);
    }
    assertTrue($threw, 'debug mode fails fast on a bad registration');
    unset($config['app.debug']);
    $config['mcp.servers'] = [];

    // --- SecurityPolicy: destructive gate, RequiresPermission, tools/list hiding ------------------

    $registry = freshRegistry();
    $registry->register(new ShopProvider());
    $policy = new SecurityPolicy();

    assertTrue($policy->isToolDenied('content', 'shop.orders.delete'), 'destructive tools are denied by default');
    assertTrue($policy->isToolHidden('content', 'shop.orders.delete'), 'disabled destructive tools are hidden');
    $config['cms.settings.eMCP.security.enable_write_tools'] = true;
    assertTrue($policy->isToolDenied('content', 'shop.orders.delete'), 'enabling write tools does not enable destructive ones');
    $config['cms.settings.eMCP.security.enable_destructive_tools'] = true;
    assertTrue(!$policy->isToolDenied('content', 'shop.orders.delete'), 'destructive tools pass when enabled');
    $config['cms.settings.eMCP.security.enable_write_tools'] = false;
    assertTrue($policy->isToolDenied('content', 'shop.orders.delete'), 'destructive tools stay behind the write switch');
    $config['cms.settings.eMCP.security.enable_write_tools'] = true;

    $_SESSION = [];
    assertTrue($policy->isToolPermitted('shop.orders.list'), 'tools without RequiresPermission are not gated here');
    assertTrue(!$policy->isToolPermitted('shop.orders.set_status'), 'no manager session (auth.mode=none, CLI) means no access');

    $evo->loggedIn = true;
    assertTrue(!$policy->isToolPermitted('shop.orders.set_status'), 'a manager without the permission is refused');
    assertTrue($policy->isToolHidden('content', 'shop.orders.set_status'), 'and does not see the tool');

    $evo->permissions = ['shop_orders'];
    assertTrue($policy->isToolPermitted('shop.orders.set_status'), 'a manager with the permission passes');
    assertTrue(!$policy->isToolHidden('content', 'shop.orders.set_status'), 'and sees the tool');

    $evo->permissions = ['shop_admin'];
    assertTrue($policy->isToolPermitted('shop.orders.delete'), 'any permission of a list is enough');
    assertTrue(!$policy->isToolPermitted('shop.broken'), 'a blank declaration refuses non-admins');

    $evo->permissions = [];
    $_SESSION['mgrRole'] = 1;
    assertTrue($policy->isToolPermitted('shop.orders.delete'), 'role 1 passes like in the manager');
    $_SESSION = [];
    $evo->loggedIn = false;

    $config['cms.settings.eMCP.security.deny_tools'] = ['shop.orders.*'];
    assertTrue($policy->isToolHidden('content', 'shop.orders.list'), 'deny_tools also hides from tools/list');
    assertTrue($policy->isToolDenied('content', 'shop.orders.list'), 'deny_tools denies the call');
    assertTrue(!$policy->isToolHidden('content', 'evo.write.cache.clear'), 'disabled-or-not, evo.write.* stays listed');
    $config['cms.settings.eMCP.security.deny_tools'] = [];

    // --- ScopePolicy: write and destructive scopes for contributed tools --------------------------

    $scopes = new ScopePolicy();
    assertTrue($scopes->resolveToolScope('shop.orders.list') === null, 'read tool needs no extra scope');
    assertTrue($scopes->resolveToolScope('shop.orders.set_status') === 'mcp:write', 'WritesSite tool needs the write scope');
    assertTrue($scopes->resolveToolScope('unknown.tool') === null, 'unknown tools need no extra scope');
    assertTrue($scopes->resolveToolScopes('shop.orders.set_status') === ['mcp:write'], 'write tool scope list');
    assertTrue($scopes->resolveToolScopes('shop.orders.delete') === ['mcp:write', 'mcp:destructive'], 'destructive tools need write + destructive');
    $config['cms.settings.eMCP.auth.destructive_scope'] = 'shop:purge';
    assertTrue($scopes->resolveToolScopes('shop.orders.delete') === ['mcp:write', 'shop:purge'], 'destructive scope is configurable');
    unset($config['cms.settings.eMCP.auth.destructive_scope']);

    // --- Backend code: ExecutesCode tools and built-in calls that carry code -------------------------

    $config['cms.settings.eMCP.security.enable_write_tools'] = true;
    $config['cms.settings.eMCP.security.enable_destructive_tools'] = true;
    $snippet = ['type' => 'Snippet', 'fields' => ['snippet' => 'return 1;']];
    $chunk = ['type' => 'chunk', 'fields' => ['snippet' => '<p>[[Wayfinder]]</p>']];
    $tvEval = ['type' => 'tv', 'fields' => ['default_text' => "  @EVAL return 1;"]];
    $tvInclude = ['type' => 'tv', 'fields' => ['display_params' => '&output=@INCLUDE /etc/passwd']];
    $tvPlain = ['type' => 'tv', 'fields' => ['default_text' => 'Hello @EVAL']];
    $docEval = ['id' => 1, 'tvs' => ['price' => '@INHERIT @SELECT 1']];
    $docArray = ['fields' => ['pagetitle' => 'x'], 'tvs' => ['tags' => ['a', '@eval return 2;']]];
    $docPlain = ['id' => 1, 'fields' => ['content' => 'Write to @evo']];

    assertTrue($registry->isCodeTool('shop.files.write') && !$registry->isCodeTool('shop.orders.set_status'), 'ExecutesCode marks a code tool');
    assertTrue($registry->isWriteTool('shop.files.write'), 'a code tool is a write tool');
    assertTrue($registry->writesCode('evo.write.elements.save', $snippet), 'saving a snippet (any case) writes PHP');
    assertTrue($registry->writesCode('evo.write.elements.save', ['type' => 'plugin']), 'saving a plugin writes PHP');
    assertTrue(!$registry->writesCode('evo.write.elements.save', $chunk), 'a chunk calling a snippet is not code');
    assertTrue($registry->writesCode('evo.write.elements.save', $tvEval), '@EVAL in a TV definition, after whitespace');
    assertTrue($registry->writesCode('evo.write.elements.save', $tvInclude), '@INCLUDE inside display_params');
    assertTrue(!$registry->writesCode('evo.write.elements.save', $tvPlain), 'a binding only counts at the start');
    assertTrue($registry->writesCode('evo.write.content.update', $docEval), '@SELECT behind @INHERIT in a TV value');
    assertTrue($registry->writesCode('evo.write.content.create', $docArray), 'a code binding in a multi-value TV');
    assertTrue(!$registry->writesCode('evo.write.content.update', $docPlain), 'plain content is not code');
    assertTrue(!$registry->writesCode('shop.orders.set_status', $snippet), 'arguments of contributed tools are not inspected');

    assertTrue($policy->isToolDenied('content', 'evo.write.elements.save', $snippet), 'code writes are off by default');
    assertTrue(!$policy->isToolDenied('content', 'evo.write.elements.save', $chunk), 'other element writes are unaffected');
    assertTrue($policy->isToolHidden('content', 'shop.files.write'), 'a disabled code tool is hidden');
    assertTrue(!$policy->isToolHidden('content', 'evo.write.elements.save'), 'elements.save stays listed');

    $config['cms.settings.eMCP.security.enable_code_tools'] = true;
    assertTrue(!$policy->isToolDenied('content', 'evo.write.elements.save', $snippet), 'code writes pass when enabled');
    $config['cms.settings.eMCP.security.enable_write_tools'] = false;
    assertTrue($policy->isToolDenied('content', 'evo.write.elements.save', $snippet), 'code writes stay behind the write switch');
    $config['cms.settings.eMCP.security.enable_write_tools'] = true;

    $_SESSION = [];
    $evo->loggedIn = true;
    $evo->permissions = ['save_template', 'save_document'];
    assertTrue(!$policy->isToolPermitted('evo.write.elements.save', $tvEval), 'save_template alone may not store @EVAL');
    assertTrue(!$policy->isToolPermitted('evo.write.content.update', $docEval), 'save_document alone may not store @SELECT');
    assertTrue($policy->isToolPermitted('evo.write.elements.save', $tvPlain), 'a plain TV needs no PHP permission here');
    assertTrue($policy->isToolHidden('content', 'shop.files.write'), 'a code tool is hidden from managers without a PHP permission');
    $evo->permissions = ['save_module'];
    assertTrue($policy->isToolPermitted('evo.write.elements.save', $tvEval), 'save_module grants backend code');
    assertTrue(!$policy->isToolHidden('content', 'shop.files.write'), 'and shows code tools');
    $evo->loggedIn = false;
    assertTrue(!$policy->isToolPermitted('evo.write.elements.save', $snippet), 'no manager session, no code');
    $evo->permissions = [];

    assertTrue($scopes->resolveToolScopes('evo.write.elements.save', $chunk) === ['mcp:write'], 'chunk needs only the write scope');
    assertTrue($scopes->resolveToolScopes('evo.write.elements.save', $snippet) === ['mcp:write', 'mcp:code'], 'snippet needs the code scope');
    assertTrue($scopes->resolveToolScopes('shop.files.write') === ['mcp:write', 'mcp:code'], 'code tools need the code scope');

    // --- JsonRpcPayload: one decode shared by the middleware ---------------------------------------

    $request = new Request('{"jsonrpc":"2.0","method":"tools/call","params":{"name":"shop.orders.delete"}}');
    assertTrue(JsonRpcPayload::method($request) === 'tools/call', 'method is read from the body');
    assertTrue(JsonRpcPayload::toolName($request) === 'shop.orders.delete', 'tool name is read from the body');
    assertTrue($scopes->resolveRequestedMethod($request) === 'tools/call', 'ScopePolicy uses the shared payload');
    assertTrue($request->reads === 1, 'the body is decoded once');
    assertTrue(JsonRpcPayload::toolName(new Request('{"method":"tools/list"}')) === null, 'no tool name outside tools/call');
    $withArgs = new Request('{"method":"tools/call","params":{"name":"evo.write.elements.save","arguments":{"type":"plugin"}}}');
    assertTrue(JsonRpcPayload::toolArguments($withArgs) === ['type' => 'plugin'], 'tool arguments are read from the body');
    assertTrue(JsonRpcPayload::toolArguments(new Request('{"method":"tools/list","params":{"arguments":{"x":1}}}')) === [], 'no arguments outside tools/call');
    $broken = new Request('not-json');
    assertTrue(JsonRpcPayload::of($broken) === null && JsonRpcPayload::of($broken) === null && $broken->reads === 1, 'invalid bodies are cached too');

    echo "ToolRegistry / extension policy unit checks passed.\n";
}
