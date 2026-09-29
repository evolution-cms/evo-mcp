<p align="center">
<a href="https://packagist.org/packages/evolution-cms/emcp"><img src="https://img.shields.io/packagist/dt/evolution-cms/emcp" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/evolution-cms/emcp"><img src="https://img.shields.io/packagist/v/evolution-cms/emcp" alt="Latest Stable Version"></a>
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

# eMCP for Evolution CMS

`eMCP` is the Evolution CMS integration layer for `laravel/mcp`.

It adapts Laravel MCP to Evo runtime with:
- Evo-native config publishing
- manager ACL and sApi scope controls
- optional async dispatch through sTask
- no Laravel app skeleton requirement
- Evo domain MCP tools for document tree (`SiteContent` + TVs)

Implementation starts with a strict MVP gate:
- web transport
- manager mode
- `initialize` + `tools/list`

Design style:
- contract-first (`TOOLSET.md` + validators)
- declarative config-first server registry (`config/mcp.php`)
- explicit handler pipeline (`validate -> authorize -> query -> map -> paginate`)

If you need full architecture and contracts, see `DOCS.md` (EN) or `DOCS.uk.md` (UA).
Public canonical tool contract: `TOOLSET.md`.
Versioning and BC policy: `PRD.md` (`API Stability Policy` section).
Operations runbook: `OPERATIONS.md`.

## Requirements
- Evolution CMS 3.5.2+
- PHP 8.3+
- Composer 2.2+
- `seiger/sapi` 1.x (installed as dependency; only used when `auth.mode = sapi_jwt`)
- `seiger/stask` 1.x (installed as dependency)

## Install
From your Evo `core` directory:

```bash
cd core
php artisan package:installrequire evolution-cms/emcp "*"
php artisan migrate
```

## Publish Config and Stubs

`core/custom/config/cms/settings/eMCP.php` is created automatically on the first request after install when it is missing, so `emcp-config` is only needed to restore the defaults. `emcp-mcp-config` changes the MCP server list, `emcp-stubs` scaffolds your own servers and tools.

Site-wide switches (write tools, manager/API endpoints, token scopes, rate limit, audit log, streaming) are also available as checkboxes in **Tools → MCP settings** (`{manager_url}/emcp/settings`, requires the `settings` permission). The page edits the same file and keeps its comments.

```bash
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-config
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-mcp-config
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-stubs
```

Published files:
- `core/custom/config/cms/settings/eMCP.php`
- `core/custom/config/mcp.php`
- `core/stubs/mcp-*.stub`

## Quick Start (Internal + External)
The default contract is concept-agnostic and follows Laravel MCP behavior first.

1. Create your MCP server/tool classes:

```bash
php artisan make:mcp-server ContentServer
php artisan make:mcp-tool HealthTool
```
Generated classes are placed in `core/custom/app/Mcp/...`.

2. Register server in `core/custom/config/mcp.php` (`servers[]`).
3. Test manager/internal route:
- `POST /{manager_prefix}/{handle}` with manager session + permission `emcp`.
4. Enable external API mode (if `sApi` installed):
- keep `mode.api=true` in `core/custom/config/cms/settings/eMCP.php`
- call `POST /{SAPI_BASE_PATH}/{SAPI_VERSION}/mcp/{handle}` with Bearer JWT and required `mcp:*` scopes.
- get JWT from `POST /{SAPI_BASE_PATH}/{SAPI_VERSION}/token` (sApi token endpoint).
5. Optional async:
- set `queue.driver=stask`, ensure `sTask` installed, use dispatch endpoint for long-running jobs.

## Personal access tokens (default API auth)
Since this version the API endpoint needs **no extra packages**: a manager user creates a
personal access token and every request runs **as that user**, with that user's role,
permissions and document groups — exactly as if they were logged into the manager.

1. Give the role the `emcp` permission (admins have it after `migrate`).
2. Open **Tools → MCP tokens** in the manager (`{manager_url}/emcp/tokens`), or run
   `php artisan emcp:token:create <username> --scopes=mcp:read,mcp:call --expires=90`.
3. Connect the agent:

```bash
claude mcp add --transport http evo https://example.com/mcp/content   --header "Authorization: Bearer emcp_..."
```

```toml
# Codex ~/.codex/config.toml
[mcp_servers.evo]
url = "https://example.com/mcp/content"
bearer_token_env_var = "EVO_MCP_TOKEN"
```

Scopes narrow a token, never widen the user: `mcp:read` (list/read), `mcp:call` (read-only
tools), `mcp:write` (`evo.write.*` tools, also gated by `security.enable_write_tools`),
`mcp:admin`. Tokens are stored hashed, can expire, and are revoked from the same page or with
`emcp:token:revoke`. `emcp:token:list` shows what exists.

`auth.mode` in `core/custom/config/cms/settings/eMCP.php` selects `pat` (default), `sapi_jwt`
(seiger/sapi JWT, now also impersonating the JWT user) or `none`.

### Write tools
`evo.write.content.update|create|publish`, `evo.write.elements.save`, `evo.write.cache.clear`
plus the read tools `evo.elements.list|get`. Each one re-checks the manager permission of the
matching manager action (`save_document`, `publish_document`, `save_chunk`, `new_snippet`, ...),
document-group access, element locks, fires the same `OnBefore*FormSave`/`On*FormSave` events
and writes a `manager_log` row, so an admin sees API changes next to browser ones.

### Tools from other extras
An extra contributes tools by implementing `EvolutionCMS\eMCP\Contracts\ToolProvider` and
registering it in its service provider's `boot()`:

```php
if (class_exists(\EvolutionCMS\eMCP\Services\ToolRegistry::class)) {
    app(\EvolutionCMS\eMCP\Services\ToolRegistry::class)->register(new MyToolProvider());
}
```

Tools are plain `Laravel\Mcp\Server\Tool` classes; mark the ones that change the site with
`EvolutionCMS\eMCP\Contracts\WritesSite` so `security.enable_write_tools` and the `mcp:write`
scope apply to them. Tool code runs as the impersonated manager, so `evo()->hasPermission()` and
the extra's own guards behave as on the page. Alternatively list classes under
`mcp.servers[].extra_tools`. Example: `elcreator/aimage` ships `aimage.*` tools this way.

### Try it in Docker
```bash
cd docker && docker compose up --build      # prints the site URL and a ready-made token
EVO_EXTRAS=elcreator/aimage AIMAGE_API_KEY=... docker compose up --build   # with extras
docker/smoke.sh                             # runs an end-to-end check against it
```

## Design Philosophy (Optional Reading)
### Why This Product Exists (4 Core Questions, Aristotle)
This is the shortest way to understand eMCP as a product, not just a package.

1. Material cause: what it consists of (hard boundaries):
- protocol/runtime from `laravel/mcp`
- Evo adapter layer (`ServiceProvider`, registry, routes, middleware, publish)
- optional access/async integrations (`sApi`, `sTask`)
- canonical contracts (`SPEC.md`, `TOOLSET.md`)

2. Formal cause: what form makes it a product (not components):
- one execution contract from request to audited response
- one policy model for manager/API access (`ACL + scopes + limits`)
- one versioned public tool contract for ecosystem consumers
- one stable extension model for third-party packages

3. Efficient cause: what puts it in motion (workflows + triggers):
- internal trigger: manager MCP call (`/{manager_prefix}/{handle}`)
- external trigger: API MCP call (`/{SAPI_BASE_PATH}/{SAPI_VERSION}/mcp/{handle}`)
- async trigger: dispatch into `sTask` worker for long operations
- lifecycle trigger: package install/publish/register/test

4. Final cause: why it is built this way:
- keep Laravel MCP semantics intact
- keep Evo integration explicit and operable
- support both internal and external MCP usage
- allow multiple orchestration strategies on top of one neutral MCP foundation

### Conceptual Model (Design Lens)
This lens helps explain architecture decisions:
- set theory: CMS data is structured sets (site -> nodes -> attributes)
- Peano sequence: workflows are ordered state transitions
- Godel limits: self-referential rule systems need strict boundaries

Practical implication:
- eMCP stays as a contract/runtime layer
- orchestration logic stays in consuming packages
- policy/audit/human gates prevent recursive rule loops from becoming unsafe

## Install Verification (1 minute)
For Gate A, use manager endpoint `/{manager_prefix}/{server_handle}` (default: `/emcp/content`).
Gate A is manager ACL protected, so run checks as a logged-in manager with `emcp` permission (session cookie required).

1. Verify `GET` returns `405` on MCP endpoint:

```bash
curl -i -X GET http://localhost/<MANAGER_PREFIX>/<SERVER_HANDLE> \
  -H 'Cookie: evo_session=<MANAGER_SESSION_COOKIE>'
```

2. Verify JSON-RPC `initialize`:

```bash
curl -i -X POST http://localhost/<MANAGER_PREFIX>/<SERVER_HANDLE> \
  -H 'Cookie: evo_session=<MANAGER_SESSION_COOKIE>' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":"init-1","method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"smoke","version":"1.0.0"}}}'
```

Expected:
- HTTP `200` for valid `initialize`.
- `MCP-Session-Id` present in response headers.
- stable HTTP `405` on `GET`.

## Register MCP Servers (Evo style)
Unlike Laravel default `routes/ai.php`, eMCP registers servers from config.

Example in `core/custom/config/mcp.php`:

```php
return [
    'redirect_domains' => ['*'],

    'servers' => [
        [
            'handle' => 'content',
            'transport' => 'web',
            'route' => '/mcp/content',
            'class' => EvolutionCMS\eMCP\Servers\ContentServer::class,
            'enabled' => true,
            'auth' => 'sapi_jwt',
            'scopes' => ['mcp:read', 'mcp:call'],
        ],
        [
            'handle' => 'content-local',
            'transport' => 'local',
            'class' => EvolutionCMS\eMCP\Servers\ContentServer::class,
            'enabled' => false,
        ],
    ],
];
```

Notes:
- Gate A manager endpoint is still `/{manager_prefix}/{handle}` (for example `/emcp/content`).
- `servers[*].route` is used by web transport registration and becomes externally relevant in API mode (Gate B+).
- `content-local` is disabled by default to avoid duplicate tool-name registration conflict with `content`.

## Access Model
- Manager/internal access: Evo permission `emcp`
- API access (via sApi): JWT scopes (`mcp:read`, `mcp:call`, `mcp:admin`)
- Domain reads (`evo.content.*`, `evo.model.*`) are read-only by default

## Ecosystem Interop
eMCP is the MCP platform layer for the Evo ecosystem:
- `LaravelMcp`: upstream protocol/runtime contract (kept intact).
- `sApi`: external API kernel + JWT route-provider discovery.
- `sTask`: async worker/task execution and progress.
- `eAi`: AI runtime can call MCP tools through manager or API mode.
- `dAi`: manager-side orchestration UI can consume eMCP tools as a stable contract.

This keeps the core declarative and neutral: one MCP foundation for multiple orchestration concepts.

## Evo Domain Tools
- Implemented now: `evo.content.search|get|root_tree|descendants|ancestors|children|siblings`
- Optional (implemented): `evo.content.neighbors|prev_siblings|next_siblings|children_range|siblings_range`
- TV-aware queries via structured `with_tvs`, `tv_filters`, `tv_order`
- `evo.model.list|get` implemented with per-model explicit allowlist projection and sensitive-field defense-in-depth blacklist

## Artisan Commands
From Laravel MCP (available via eMCP adapter):

```bash
php artisan make:mcp-server ContentServer
php artisan make:mcp-tool ListResourcesTool
php artisan make:mcp-resource DocsResource
php artisan make:mcp-prompt SummaryPrompt
php artisan mcp:start content-local
```

For `mcp:start content-local`, first enable `content-local` in `core/custom/config/mcp.php` and disable conflicting server entries if they expose identical tool names.

### Third-party servers

Register project-specific tools on a separate `Laravel\Mcp\Server` class and add
that class as its own entry in `core/custom/config/mcp.php`. Third-party tool
names must not use the reserved `evo.*` namespace. Do not subclass the package
`ContentServer` only to append project tools, because the inherited `evo.*`
toolset is package-owned and the registry will reject that external server.

`php artisan emcp:test --server=<handle>` runs generic `initialize` and
`tools/list` checks for third-party servers. When the selected class is the
canonical eMCP `ContentServer`, it also verifies the required Evolution toolset.
Use `php artisan emcp:list-servers` to see both accepted servers and concrete
reasons why configured entries were rejected.

eMCP operational commands:
- `php artisan emcp:test`
- `php artisan emcp:list-servers`
- `php artisan emcp:sync-workers`
- `composer run governance:update-lock`
- `composer run ci:check`
- `composer run benchmark:run`
- `composer run benchmark:leaderboard`
- `composer run test:integration:clean-install`

## Repository Checks (for first run in package workspace)
If you are validating this repository directly:

```bash
composer run check
make test
composer run ci:check
make benchmark
make leaderboard
```

These checks validate `composer.json` and run PHP syntax lint across package sources.

One-click demo + full MCP verification:

```bash
make demo-all
```

This target installs demo Evo, starts `php -S`, issues sApi JWT, runs `php artisan emcp:test`, then runs `composer run test` with HTTP runtime integration enabled.
After run, detailed evidence is written to:
- `demo/logs.md` (token/masked auth info, MCP request payloads, HTTP statuses, responses, manual verification commands, plus negative probes: 401/403/413/415/409/429 and `evo.model.get(User)` sanity)
- `demo/logs.md` also includes local `sTask` lifecycle proof (`queued -> completed`) via `php artisan stask:worker` in demo runtime.
- `/tmp/emcp-demo-php-server.log` (php built-in server log)

If GitHub API auth is needed during install, pass token via ENV (same pattern as `evolution`):

```bash
GITHUB_PAT=ghp_xxx make demo-all
```

Fallback ENV names are also supported: `GITHUB_TOKEN`, `GH_TOKEN`.

Manual content-read MCP examples (same calls used in `demo/logs.md`):

```bash
# list tools
curl -sS -H 'Content-Type: application/json' -H 'Authorization: Bearer <TOKEN>' \
  -d '{"jsonrpc":"2.0","id":"tools-1","method":"tools/list","params":{}}' \
  'http://127.0.0.1:8787/api/v1/mcp/content'

# read content slice from DB
curl -sS -H 'Content-Type: application/json' -H 'Authorization: Bearer <TOKEN>' \
  -d '{"jsonrpc":"2.0","id":"search-1","method":"tools/call","params":{"name":"evo.content.search","arguments":{"limit":3,"offset":0}}}' \
  'http://127.0.0.1:8787/api/v1/mcp/content'

# read one document
curl -sS -H 'Content-Type: application/json' -H 'Authorization: Bearer <TOKEN>' \
  -d '{"jsonrpc":"2.0","id":"get-1","method":"tools/call","params":{"name":"evo.content.get","arguments":{"id":1}}}' \
  'http://127.0.0.1:8787/api/v1/mcp/content'
```

Optional runtime integration check (against deployed environment):

```bash
EMCP_INTEGRATION_ENABLED=1 \
EMCP_BASE_URL="https://example.org" \
EMCP_API_PATH="/api/v1/mcp/{server}" \
EMCP_API_TOKEN="<jwt>" \
EMCP_SERVER_HANDLE="content" \
EMCP_DISPATCH_CHECK=1 \
composer run test:integration:runtime
```

CI release note:
- `.github/workflows/ci.yml` runs `demo-runtime-proof`, `runtime-integration`, and `migration-matrix` (`sqlite/mysql/pgsql`) on `release/*` pushes.
- Configure branch protection to make these jobs required for RC/release merges.

## Async (sTask-first)
If `queue.driver=stask` and `sTask` is installed, eMCP can run long MCP calls via worker `emcp_dispatch`.
If sTask is missing, fallback behavior follows `queue.failover` (`sync` or `fail`).

## Security Notes
- Keep secrets in `.env` or `core/custom/config/*`.
- Audit logs must redact tokens/secrets.
- Use tool denylist and server allowlist for production hardening.

## Security Defaults
- deny-by-default for manager/API without explicit access.
- `security.enable_write_tools=false` by default.
- sensitive key redaction in logs is mandatory.
- API scope checks (`mcp:read|call|admin`) are required in Gate B+.
- `depth/limit/payload` limits should stay enabled.

Security release checklist: `SECURITY_CHECKLIST.md`.
Threat model: `THREAT_MODEL.md`.
Architecture freeze: `ARCHITECTURE_FREEZE_CHECKLIST.md`.

## License
MIT (`LICENSE`).
