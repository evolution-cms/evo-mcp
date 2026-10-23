# DOCS — eMCP (Evolution CMS + Laravel MCP)

This document describes how `eMCP` integrates `laravel/mcp` into Evolution CMS.
It is the implementation-oriented guide aligned with `PRD.md` and `SPEC.md`.

Contract boundary:
- `SPEC.md` and `TOOLSET.md` are normative.
- `DOCS.md` describes how to implement and operate those contracts.
- `OPERATIONS.md` is the day-2 operator runbook (profiles, health checks, triage).

Status marker:
- Contract target: `SPEC 1.0-contract`.
- Runtime baseline in current repository: `Gate C baseline implemented (full validation pending)`.

## 1) Overview
`eMCP` is a thin Evo-native adapter around `laravel/mcp`.

Core goals:
- keep upstream MCP runtime and protocol behavior
- adapt registration/config/routes for Evo architecture
- enforce ACL/scopes and enterprise security controls
- support both internal manager usage and external API clients

Integration layers:
- **Protocol layer**: `laravel/mcp` (Server, Registrar, transports, JSON-RPC methods)
- **Adapter layer**: `eMCP` provider, registry, middleware, publishing
- **API layer**: optional `sApi` route provider with JWT scopes
- **Async layer**: optional `sTask` worker (`emcp_dispatch`)

## 2) Delivery Order (Risk-First)
Implementation order is mandatory:
- Gate A: web transport + manager route + manager ACL (`emcp`) + `initialize` + `tools/list` + `GET=405`
- Gate B: API access layer (scope engine, basic rate limit, `sApi` provider)
- Gate C: async (`sTask` worker, payload contract, failover, idempotency)
- Gate D: security hardening + DX commands

Minimal first release is Gate A only.

## 2.1) Laravel MCP Parity Contract
eMCP keeps upstream `laravel/mcp` behavior as the baseline:
- `GET` on MCP transport route returns `405`.
- `POST` processes JSON-RPC messages.
- `MCP-Session-Id` is passed through request/response.
- `202` is preserved for no-reply notification flows.
- SSE transport response uses `text/event-stream` when streaming is enabled.
- Upstream command surface (`make:mcp-*`, `mcp:start`, `mcp:inspector`) remains available.

eMCP-specific logic is additive (ACL/scopes/policies), not a protocol rewrite.

## 2.2) Ecosystem Interop Map
- `sApi`: external exposure for MCP endpoints through route providers and JWT scopes.
- `sTask`: async dispatch for long-running MCP calls (`emcp_dispatch` worker).
- `eAi`: AI runtime can consume eMCP tools in manager or API mode.
- `dAi`: manager orchestration UI can consume stable eMCP tool contracts.

Boundary rule:
- eMCP provides protocol/runtime/policy contracts.
- orchestration concepts live in consuming packages, not in eMCP core.

## 2.3) Fast Path: One MCP Server, Internal + External
1. Generate primitives:
```bash
php artisan make:mcp-server ContentServer
php artisan make:mcp-tool HealthTool
```
Generated classes are placed under `core/custom/app/Mcp/...`.
2. Register server entry in `core/custom/config/mcp.php`.
3. Verify manager/internal call:
- `POST /{manager_prefix}/{handle}` with manager session and `emcp` permission.
4. Verify external API call (with `sApi` installed):
- `POST /{SAPI_BASE_PATH}/{SAPI_VERSION}/mcp/{handle}`
- `Authorization: Bearer <jwt>` with required `mcp:*` scopes.
5. Optional async mode:
- `queue.driver=stask` + dispatch endpoint + task progress tracking.

## User Guide

## 3) Requirements
Mandatory:
- Evolution CMS 3.5.2+
- PHP 8.3+
- Composer 2.2+
- `seiger/sapi` 1.x
- `seiger/stask` 1.x (from 1.0.10) or 2.x (2.x needs PHP 8.4)

## 4) Install
From Evo `core` directory:

```bash
cd core
php artisan package:installrequire evolution-cms/emcp "*"
php artisan migrate
```

## 5) Publish Resources
`core/custom/config/cms/settings/eMCP.php` is created automatically on the first request after install when it is missing, so `emcp-config` is only needed to restore the defaults. `emcp-mcp-config` changes the MCP server list, `emcp-stubs` scaffolds your own servers and tools.

Site-wide switches (write tools, manager/API endpoints, token scopes, rate limit, audit log, streaming) are also available as checkboxes in **Tools → MCP settings** (`{manager_url}/emcp/settings`, requires the `settings` permission). The page edits the same file and keeps its comments.

```bash
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-config
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-mcp-config
php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-stubs
```

Published targets:
- `core/custom/config/cms/settings/eMCP.php`
- `core/custom/config/mcp.php`
- `core/stubs/mcp-server.stub`
- `core/stubs/mcp-tool.stub`
- `core/stubs/mcp-resource.stub`
- `core/stubs/mcp-prompt.stub`

## 6) Configuration Reference

## 6.1 `core/custom/config/cms/settings/eMCP.php`
Recommended baseline:

Phase labels:
- `[MVP]` `enable`, `mode.internal`, `route.manager_prefix`, `acl.permission`.
- `[Gate B+]` `mode.api`, `auth.*`, `rate_limit.*`, `limits.*`, `security.allow_servers`, `security.deny_tools`, `domain.*`, `actor.*`.
- `[Gate C+]` `queue.*`, async/idempotency behavior.
- `[Gate E]` `logging.audit_enabled`, hardening/redaction tuning.

```php
return [
    'enable' => true,

    'mode' => [
        'internal' => true,
        'api' => true,
    ],

    'route' => [
        'manager_prefix' => 'emcp',
        'api_prefix' => 'mcp',
    ],

    'auth' => [
        'mode' => 'sapi_jwt',
        'require_scopes' => true,
        'scope_map' => [
            'mcp:read' => ['initialize', 'ping', 'tools/list', 'resources/list', 'resources/read', 'prompts/list', 'prompts/get', 'completion/complete'],
            'mcp:call' => ['tools/call'],
            'mcp:admin' => ['admin/*'],
        ],
    ],

    'acl' => [
        'permission' => 'emcp',
    ],

    'queue' => [
        'driver' => 'stask',
        'failover' => 'sync',
    ],

    'rate_limit' => [
        'enabled' => true,
        'per_minute' => 60,
    ],

    'limits' => [
        'max_payload_kb' => 256,
        'max_result_items' => 100,
        'max_result_bytes' => 1048576,
    ],

    'logging' => [
        'channel' => 'emcp',
        'audit_enabled' => true,
        'redact_keys' => ['authorization', 'token', 'jwt', 'secret', 'cookie', 'password', 'api_key'],
    ],

    'security' => [
        'allow_servers' => ['*'],
        'deny_tools' => [],
        'enable_write_tools' => false,
        'enable_destructive_tools' => false,
        'enable_code_tools' => false,
    ],

    'domain' => [
        'content' => [
            'max_depth' => 6,
            'max_limit' => 100,
            'max_offset' => 5000,
        ],
        'models' => [
            'max_offset' => 5000,
            'allow' => [
                'SiteTemplate', 'SiteTmplvar', 'SiteTmplvarContentvalue',
                'SiteSnippet', 'SitePlugin', 'SiteModule', 'Category',
                'User', 'UserAttribute', 'UserRole', 'Permissions', 'PermissionsGroups', 'RolePermissions',
            ],
        ],
    ],

    'actor' => [
        'mode' => 'initiator',
        'service_username' => 'MCP',
        'service_role' => 'MCP',
        'block_login' => true,
    ],
];
```

## 6.2 `core/custom/config/mcp.php`
`eMCP` uses this file as the MCP server registry.

Example:

```php
return [
    'redirect_domains' => [
        '*',
    ],

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

Note:
- `content-local` is disabled by default to avoid duplicate tool-name registration conflicts with `content`.
- Enable local transport only when conflicting server entries are disabled or use non-overlapping tool names.

Validation rules:
- `handle` must be unique
- `class` must exist and extend `Laravel\Mcp\Server`
- `transport` must be `web` or `local`
- `enabled=true` to register

Optional per-server overrides:
- `scope_map`
- `limits.max_payload_kb`
- `limits.max_result_items`
- `rate_limit.per_minute`
- `security.deny_tools`

Route semantics:
- Gate A manager endpoint is `/{manager_prefix}/{handle}`.
- `servers[*].route` is the web transport route binding and is externally relevant in API mode (Gate B+).

## 6.3 Configuration Presets (Practical)
Use these presets to reduce setup friction:

- `manager-only`:
  - `mode.internal=true`, `mode.api=false`
  - use manager endpoint only (`/{manager_prefix}/{handle}`)
- `api-only`:
  - `mode.internal=false`, `mode.api=true`
  - require `sApi` + JWT scopes
- `hybrid` (recommended default):
  - `mode.internal=true`, `mode.api=true`
  - same tool contract available for manager and API consumers

Async add-on (any preset):
- `queue.driver=stask` for long-running calls.
- `queue.failover=sync` for resilient fallback on installations without `sTask`.

## 7) Server Registration Model
Upstream Laravel MCP expects `routes/ai.php`.
`eMCP` replaces this with config-first registration for Evo.

Mapping:
- `transport=web` -> `Mcp::web(route, class)`
- `transport=local` -> `Mcp::local(handle, class)`

This keeps upstream runtime behavior while fitting Evo package architecture.

## 7.0) Extension Points (Ecosystem)
- Add server entries in `core/custom/config/mcp.php` with unique `handle`.
- Extend per-server policy via `scope_map`, `limits`, `rate_limit`, `security.deny_tools`.
- Add tools/resources/prompts through generators (`make:mcp-*`) and shared stubs.
- Contribute tools to an existing server from another extra: see 7.0.1.

## 7.0.1) Extension Tool Contract
An extra adds tools to a server in one of two ways:

```php
// 1. A ToolProvider, registered from the extra's service provider boot()
if (interface_exists(\EvolutionCMS\eMCP\Contracts\ToolProvider::class)) {
    app(\EvolutionCMS\eMCP\Services\ToolRegistry::class)->register(new ShopToolProvider());
}

// 2. Class names in core/custom/config/mcp.php
'servers' => [['handle' => 'content', /* ... */ 'extra_tools' => [\Shop\Mcp\OrdersListTool::class]]],
```

`ShopToolProvider::server()` returns the server handle (`content` for the default server) and
`tools()` the tool classes.

Registration rules (SPEC 6.4, 6.5):
- A tool is a `Laravel\Mcp\Server\Tool` subclass with a `#[Name('vendor.domain.action')]`
  attribute. Without `#[Name]` the name is what Laravel MCP derives (the `$name` property, else
  the kebab-cased class name); a tool that overrides `name()` and has no attribute is rejected,
  because the policy layer could not recognise it on call.
- `evo.*` names are reserved for eMCP. Names are unique: the first registration wins.
- A rejected tool is not served at all. In debug mode (`app.debug`) registration throws; otherwise
  a warning goes to the `emcp` log channel.

Markers and contracts (all optional, in `EvolutionCMS\eMCP\Contracts`):

| Contract | Effect |
|---|---|
| `WritesSite` | Write tool: refused unless `security.enable_write_tools` is on and the token has `auth.write_scope` (`mcp:write`). Listed either way. |
| `DestructiveSite` (extends `WritesSite`) | Deletes or irreversibly overwrites data. Also needs `security.enable_destructive_tools` and `auth.destructive_scope` (`mcp:destructive`); hidden from `tools/list` while disabled; calls are marked in the audit log (`extra.destructive`, `extra.reversible`, `extra.source = mcp`). Add `public static function reversible(): bool` returning true for soft deletes. |
| `ExecutesCode` (extends `WritesSite`) | Stores code the server executes: PHP, an executable file, an `@EVAL` / `@SELECT` / `@INCLUDE` binding, SQL. Also needs `security.enable_code_tools`, `auth.code_scope` (`mcp:code`) and, for the token owner, a permission that already grants backend code in the manager (`save_snippet`, `save_plugin` or `save_module`, as Evo's `BackendCodePermissions` defines it). Hidden from `tools/list` while disabled or not permitted; calls are marked in the audit log (`extra.executes_code`). Mark the whole tool if any of its calls can write code. |
| `RequiresPermission` | `public static function permission(): string\|array` returns an Evo permission, or a list where any one is enough. Checked for the token owner before the tool runs (`403 permission_denied`), on every transport; the tool is hidden from `tools/list` for users without it. Role 1 always passes. No manager identity (`auth.mode = none`, CLI, an async worker) means no access. |

`tools/list` also gets `readOnlyHint` / `destructiveHint` annotations from these markers (hints a
tool sets itself are kept). They are hints for the client, not protection:
- `DestructiveSite`, `ExecutesCode`: `destructiveHint: true`.
- `WritesSite`: only `readOnlyHint: false`. MCP reads a missing `destructiveHint` as true, so a
  tool that only adds data says so itself with `#[IsDestructive(false)]`.
- Built-in: `evo.write.content.update` and `evo.write.elements.save` overwrite existing data with
  no revision to return to, so they are `destructiveHint: true`; `content.create`,
  `content.publish` and `cache.clear` are `false`; `evo.*` read tools are `readOnlyHint: true`.

Built-in write tools pass the same code gate per call, judged from their arguments: saving a
`snippet` or `plugin` with `evo.write.elements.save`, a TV definition with a code binding in
`elements`, `default_text` or `display_params`, or a document whose field or TV value starts with
`@EVAL` / `@SELECT` (also behind `@INHERIT`). Templates and chunks are ordinary writes: they only
call existing snippets. Binding detection uses Evo's `TvBindingGuard` when the core has it.

On an older eMCP without these contracts, check `interface_exists(...)` and do the same checks in
the tool itself. With them, keep the in-tool checks for what the tool touches (document access,
ownership): the contracts are an entry gate, not a replacement.

### Identity rules
- The acting user is `evo()->getLoginUserID('mgr')`, nothing else. Never take a `user_id` from
  tool arguments and never read `$_SESSION` directly.
- With `auth.mode = none` there is no manager identity: refuse (`forbidden`) instead of acting as
  "the first admin" or any fallback user.
- For queued work store the owner's id in the task when it is created; do not rely on the worker's
  session, which belongs to nobody.
- Somebody else's personal resource (a job, a draft, a token) is answered as `not_found`, so ids
  cannot be probed.

### Errors
Return `EvolutionCMS\eMCP\Support\ToolError::make($code, $message, $status = 422, $extra = [])`
from `handle()`. The result has `isError: true` and one JSON text block:

```json
{"ok": false, "error": "ambiguous", "message": "Several orders match.", "status": 409,
 "candidates": [{"id": 12, "title": "Order #12"}, {"id": 14, "title": "Order #14"}]}
```

- Codes: `forbidden`, `not_found`, `ambiguous`, `invalid`, `refused`, `store_failure`, or the
  extra's own with a namespace prefix (`aimage.quota_exceeded`). Any other code throws.
- `errors`: per-field messages (`ToolError::fromValidation($e)` converts a `ValidationException`).
- `candidates`: only `id` and `title` (a `name` counts as title); at most 10 are kept
  (`candidates_truncated: true` when more were given). Put nothing there the user may not see.
- eMCP's own tools still answer with `ValidationException` messages (their output is fixed by
  golden fixtures).

## 7.1) Evo Domain Tool Profiles
Canonical source for tool names/params/examples/errors is `TOOLSET.md`.

Primary domain profile is document tree access via `SiteContent`:
- `evo.content.search`
- `evo.content.get`
- `evo.content.root_tree`
- `evo.content.descendants`
- `evo.content.ancestors`
- `evo.content.children`
- `evo.content.siblings`

Optional (implemented) tree tools:
- `evo.content.neighbors`
- `evo.content.prev_siblings`
- `evo.content.next_siblings`
- `evo.content.children_range`
- `evo.content.siblings_range`

TV support is part of the contract:
- `with_tvs` maps to `withTVs`
- structured `tv_filters` maps to `tvFilter`
- structured `tv_order` maps to `tvOrderBy`
- `tags_data` maps to `tagsData`
- `order_by_date` maps to `orderByDate`

Safety constraints:
- reject raw `tvFilter` DSL strings from client payloads
- allow only approved operators/casts
- enforce `depth/limit/offset` caps from config

Contract-first execution style:
- each tool call follows `validate -> authorize -> query -> map -> paginate`
- one tool should map to one explicit handler/procedure
- hidden query/policy side effects outside the pipeline are discouraged

Orchestration execution profile (post-MVP):
- `Intent -> PolicyCheck -> Task(s) -> EvidenceTrace -> ApprovalGate`
- planner actions should be constrained by policy-valid action sets
- intent/task/evidence linkage should be auditable end-to-end

Model catalog profile (read-only by default):
- `evo.model.list`
- `evo.model.get`

Default allowed models:
- `SiteTemplate`, `SiteTmplvar`, `SiteTmplvarContentvalue`
- `SiteSnippet`, `SitePlugin`, `SiteModule`, `Category`
- `User`, `UserAttribute`, `UserRole`, `Permissions`, `PermissionsGroups`, `RolePermissions`

Sensitive fields are always excluded/redacted:
- `password`, `cachepwd`, `verified_key`, `refresh_token`, `access_token`, `sessionid`

## Implementation Notes

## 8) Access Control

## 8.1 Evo permissions
Migrations should create:
- permission group: `eMCP` (or project-selected shared group)
- permissions:
- `emcp` (access)
- `emcp_manage` (management actions)
- `emcp_dispatch` (async dispatch)

Delivery gates:
- Gate A (MVP): `emcp` is required.
- Gate B+: add `emcp_manage`.
- Gate C+: add `emcp_dispatch`.

Default assignment:
- role `1` (admin) receives all `emcp*` permissions

## 8.2 API scope policy (sApi mode)
Minimum scope policy:
- `mcp:read`: `initialize`, `ping`, list/read/get methods
- `mcp:call`: `tools/call`
- `mcp:admin`: server admin/service actions
- `mcp:write` (`auth.write_scope`): additionally required by `evo.write.*` and `WritesSite` tools
- `mcp:destructive` (`auth.destructive_scope`): additionally required by `DestructiveSite` tools;
  a token issued with it also gets `mcp:write`, `mcp:call` and `mcp:read`
- `mcp:code` (`auth.code_scope`): additionally required by calls that store backend code (see
  `ExecutesCode`); a token issued with it also gets `mcp:write`, `mcp:call` and `mcp:read`

Rules:
- If `auth.require_scopes=true`, scope check is mandatory.
- `*` in token scopes grants full MCP access.

## 9) Auth Modes
Supported modes:
- `sapi_jwt` (default): uses `sApi` JWT middleware attributes
- `none` (restricted/internal scenarios only)

Behavior contract:
- Missing auth configuration must not break package boot.

## 10) Routes and Transports

## 10.1 Manager routes
Under `mgr` middleware and `emcp` permission:
- `POST /{manager_prefix}/{server}`
- `POST /{manager_prefix}/{server}/dispatch`

Rules:
- `GET` on MCP endpoint returns `405`
- `POST` expects JSON-RPC body
- pass through `MCP-Session-Id`

## 10.2 API routes (sApi)
Via `McpRouteProvider` (`RouteProviderInterface`):
- `POST /mcp/{server}`
- `POST /mcp/{server}/dispatch`

Recommended middleware chain:
- `emcp.jwt`
- `emcp.scope`
- `emcp.actor`
- `emcp.rate`

`McpRouteProvider` removes upstream `sapi.jwt` from MCP routes and uses `emcp.jwt` as the single JWT middleware.

Error handling policy:
- transport/auth/middleware failures -> HTTP status (`401/403/405/413/415`) with non-JSON-RPC error body.
- JSON-RPC dispatch failures -> HTTP `200` + JSON-RPC `error` (`-32700`, `-32600`, `-32601`, `-32602`, `-32603`).

For exact normative mapping and error body shape, use `SPEC.md`.

## 10.3 Streaming
When MCP method streams iterable responses, response must use:
- `Content-Type: text/event-stream`
- optional `MCP-Session-Id` response header

Environment notes (Gate B+):
- Nginx/Proxy: disable buffering for MCP streaming routes.
- PHP-FPM/FastCGI: configure output buffering/flush for incremental event delivery.
- Proxy/FPM timeouts must be tuned for long-running streams.

## 11) Async via sTask

## 11.1 Worker registration
If `sTask` is installed, register worker:
- `identifier`: `emcp_dispatch`
- `scope`: `eMCP`
- `class`: `EvolutionCMS\eMCP\sTask\McpDispatchWorker`
- `active`: `true`

## 11.2 Payload contract
Async payload should include:
- `server_handle`
- `jsonrpc_method`
- `jsonrpc_params`
- `request_id`
- `session_id`
- `trace_id`
- `idempotency_key`
- `actor_user_id`
- `initiated_by_user_id`
- `context` (`mgr|api|cli`)
- `attempts`
- `max_attempts`

## 11.3 Failover
If `sTask` is unavailable:
- `queue.failover=sync` -> execute synchronously
- `queue.failover=fail` -> return controlled error

## 12) Actor Resolution
Context identity fields:
- `actor_user_id`
- `initiated_by_user_id`
- `context`
- `trace_id`

Resolution strategy:
- manager mode -> current manager user
- sApi mode -> JWT user (`sapi.jwt.user_id`) when available
- service mode -> dedicated service account (`actor.mode=service`)

## 13) Logging and Audit

## 13.1 Channel
`logging.channels.emcp` daily channel:
- file: `core/storage/logs/emcp-YYYY-MM-DD.log`
- rotation: `LOG_DAILY_DAYS`

## 13.2 Audit fields
Audit events should include:
- `timestamp`
- `request_id`
- `trace_id`
- `server_handle`
- `method`
- `status`
- `actor_user_id`
- `context`
- `duration_ms`
- `task_id` (if async)

## 13.3 Redaction
Never log raw values of:
- `authorization`
- `token`
- `jwt`
- `secret`
- `cookie`
- `password`
- `api_key`

## 14) Multilingual Support
Required language files (`lang/{locale}/global.php`) for all supported locales:
`az`, `be`, `bg`, `cs`, `da`, `de`, `en`, `es`, `fa`, `fi`, `fr`, `he`, `it`, `ja`, `nl`, `nn`, `pl`, `pt`, `sk`, `sv`, `uk`, `zh`.

Minimum keys:
- `title`
- `permissions_group`
- `permission_access`
- `permission_manage`
- `permission_dispatch`
- `errors.forbidden`
- `errors.scope_denied`
- `errors.server_not_found`
- `errors.invalid_payload`

## 15) Artisan Commands
Upstream MCP commands expected through adapter:

```bash
php artisan make:mcp-server ContentServer
php artisan make:mcp-tool ListResourcesTool
php artisan make:mcp-resource DocsResource
php artisan make:mcp-prompt SummaryPrompt
php artisan mcp:start content-local
php artisan mcp:inspector content-local
```

Before `mcp:start content-local`, enable `content-local` in `core/custom/config/mcp.php` and disable conflicting server entries if they expose identical tool names.

Available eMCP operational commands:

```bash
php artisan emcp:test
php artisan emcp:list-servers
php artisan emcp:sync-workers
composer run governance:update-lock
composer run ci:check
composer run benchmark:run
composer run benchmark:leaderboard
composer run test:integration:clean-install
EMCP_INTEGRATION_ENABLED=1 EMCP_BASE_URL="https://example.org" EMCP_API_PATH="/api/v1/mcp/{server}" EMCP_API_TOKEN="<jwt>" composer run test:integration:runtime
```

## 16) Troubleshooting
- **401/403 on API calls**:
  check JWT scopes and `auth.require_scopes`.

- **403 in manager**:
  verify role has `emcp` permission.

- **Server not found**:
  verify `mcp.php` entry (`enabled`, `handle`, class path).

- **Streaming not working**:
  check server/client supports SSE and proxy buffering settings.

- **Async dispatch not running**:
  verify `sTask` installed, worker registered, and worker process active.

## 17) Quick File Map
- Product requirements: `PRD.md`
- Technical contract: `SPEC.md`
- Canonical tool contract: `TOOLSET.md`
- Quick start: `README.md`, `README.uk.md`
- Deep docs: `DOCS.md`, `DOCS.uk.md`
- Execution plan: `TASKS.md`
- Security review: `SECURITY_CHECKLIST.md`
- Threat model: `THREAT_MODEL.md`
- Formal audit: `PLATFORM_AUDIT.md`
- Architecture freeze gate: `ARCHITECTURE_FREEZE_CHECKLIST.md`
