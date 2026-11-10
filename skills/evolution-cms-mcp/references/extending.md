# Extending eMCP

Full contract: `DOCS.md` section 7.0.1 in the eMCP package.

## Contents

- [Add tools from an extra](#add-tools-from-an-extra)
- [Markers](#markers)
- [Identity and errors](#identity-and-errors)
- [Own server](#own-server)
- [Test](#test)

## Add tools from an extra

1. Write each tool as a `Laravel\Mcp\Server\Tool` subclass with `#[Name('vendor.domain.action')]`
   and `#[Description('...')]`. `evo.*` is reserved; the first registration of a name wins.
2. Implement `EvolutionCMS\eMCP\Contracts\ToolProvider`: `server()` returns the server handle
   (`content`), `tools()` the tool class names.
3. Register it from the extra's service provider `boot()`, guarded so the extra works without eMCP:

```php
$registry = 'EvolutionCMS\eMCP\Services\ToolRegistry';
if (interface_exists('EvolutionCMS\eMCP\Contracts\ToolProvider') && class_exists($registry)) {
    $this->app->make($registry)->register(new Mcp\ToolProvider());
}
```

Alternative without code in the extra: list tool classes under
`servers[].extra_tools` in `core/custom/config/mcp.php`.

Minimal tool:

```php
use EvolutionCMS\eMCP\Contracts\RequiresPermission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('shop.orders.list')]
#[Description('List the newest shop orders the acting manager may see.')]
final class OrdersListTool extends Tool implements RequiresPermission
{
    public static function permission(): string|array { return 'shop_orders'; }

    public function schema(JsonSchema $schema): array
    {
        return ['limit' => $schema->integer()->min(1)->max(50)->required()];
    }

    public function handle(Request $request): ResponseFactory
    {
        $limit = (int) $request->validate(['limit' => 'required|integer|min:1|max:50'])['limit'];
        return Response::structured(['items' => Order::latest()->limit($limit)->get(['id', 'total'])->toArray()]);
    }
}
```

aIMage (`src/Mcp/WorkbenchTool.php`) and aMount (`src/Mcp/AmountTool.php`) are working references
with a shared base class per extra.

## Markers

All in `EvolutionCMS\eMCP\Contracts`:

| Marker | Effect |
|---|---|
| `WritesSite` | Needs `enable_write_tools` + `mcp:write`; still listed when disabled |
| `DestructiveSite` | Also `enable_destructive_tools` + `mcp:destructive`; hidden while disabled; audited. `reversible(): bool` for soft deletes |
| `ExecutesCode` | Also `enable_code_tools` + `mcp:code` + `save_snippet`/`save_plugin`/`save_module`; hidden while disabled |
| `RequiresPermission` | `permission(): string|array` checked before the call; hidden from users without it |

Markers are an entry gate. Keep the extra's own checks (ownership, document access, file root)
inside the tool.

## Identity and errors

- The acting user is `evo()->getLoginUserID('mgr')`. Never take a user id from arguments.
- With no manager identity (`auth.mode = none`, CLI, queue worker) refuse with `forbidden`.
- Store the owner id in queued work when it is created.
- Answer someone else's resource as `not_found`.
- Return errors with `EvolutionCMS\eMCP\Support\ToolError::make($code, $message, $status, $extra)`;
  codes `forbidden`, `not_found`, `ambiguous`, `invalid`, `refused`, `store_failure` or a
  prefixed own code (`shop.out_of_stock`). `candidates` holds at most 10 `{id, title}`.

## Own server

For a separate toolset, generate a server and register it as its own entry:

```bash
php artisan make:mcp-server ShopServer
php artisan make:mcp-tool OrdersListTool
```

```php
// core/custom/config/mcp.php
'servers' => [
    ['handle' => 'shop', 'transport' => 'web', 'route' => '/mcp/shop',
     'class' => App\Mcp\Servers\ShopServer::class, 'enabled' => true,
     'scopes' => ['mcp:read', 'mcp:call']],
],
```

Do not subclass the package `ContentServer`; the registry rejects external servers that inherit
the `evo.*` toolset. A `transport: local` entry can be started over stdio with
`php artisan mcp:start <handle>`.

## Test

```bash
php artisan emcp:list-servers          # accepted servers and rejection reasons
php artisan emcp:test --server=shop    # initialize + tools/list
```

Try a tool through the manager endpoint `POST /{manager_prefix}/{handle}` with a manager session,
or through `POST /mcp/{handle}` with a token.
