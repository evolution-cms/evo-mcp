# Changelog

## [Unreleased]
### Added
- Personal access tokens (`auth.mode = pat`, now the default): `emcp_tokens` table, `Authorization: Bearer emcp_...` endpoint at `/{api_prefix}/{server}` without sApi, self-service **Tools → MCP tokens** manager page, `emcp:token:create|list|revoke` commands.
- API requests now impersonate the token/JWT owner (`ImpersonateManagerUser`): `evo()->isLoggedIn('mgr')`, `hasPermission()`, document groups and locks reflect that user; the `emcp` permission is required in API mode too.
- Content read tools respect document groups (`use_udperms`) and `view_unpublished`; model catalog reads require the permission of the matching manager screen.
- `evo.elements.list|get` and write tools `evo.write.content.update|create|publish`, `evo.write.elements.save`, `evo.write.cache.clear` (behind `security.enable_write_tools` and the `mcp:write` scope).
- `docker/` compose setup with a preinstalled site and smoke test.
- `core/custom/config/cms/settings/eMCP.php` is published automatically on first boot when missing (plain `composer require` never runs `vendor:publish`).
- **Tools → MCP settings** manager page (`{manager_url}/emcp/settings`, `settings` permission): checkboxes for the boolean switches, saved into that file with its comments kept.
- Extension tool contract (DOCS 7.0.1): `RequiresPermission` (checked centrally on every transport, `403 permission_denied`, tool hidden from `tools/list` without it), `DestructiveSite` (behind `security.enable_destructive_tools` and the new `mcp:destructive` scope, marked in the audit log), `Support\ToolError` for structured `{ok:false, error, message}` results, and `readOnlyHint` / `destructiveHint` annotations derived from the markers.
- `tools/list` leaves out tools matched by `deny_tools`.
- Backend code gate: `ExecutesCode` marker, `security.enable_code_tools` (off by default) and the `mcp:code` scope. `evo.write.elements.save` for snippets, plugins and TVs with `@EVAL` / `@SELECT` / `@INCLUDE` bindings, and document TV values with code bindings, now need them plus a `save_snippet` / `save_plugin` / `save_module` permission (Evo's `BackendCodePermissions`), even when the user has `save_template` or `save_document`.
### Changed
- `seiger/sapi` and `seiger/stask` have version constraints (`^1.0`, `^1.0.10 || ^2.0`) instead of `*`; `composer validate --strict` passes without warnings again.
- `ToolRegistry` follows SPEC 6.4/6.5: contributed tools in `evo.*`, with a duplicate name, or not a `Tool` are rejected (throws in debug mode, logged otherwise).
- The JSON-RPC body is decoded once per request and shared by the scope and rate-limit middleware.
- `destructiveHint` is `true` for `evo.write.content.update` and `evo.write.elements.save` (they overwrite without a revision); `WritesSite` tools no longer get `destructiveHint: false` they did not declare.
### Fixed
- A contributed tool without `#[Name]` was invisible to the write gates: it was served under its class-derived name while the policy layer looked only at the attribute. The registry now resolves names the way Laravel MCP does and rejects tools it cannot name.
- `evo.model.list|get` for `User`, `UserRole` and `Permissions` selected columns that do not exist in Evolution CMS 3.x (`isfrontend`, `frames`, ...) and failed with an SQL error; allowlists now match the 3.x schema.


All notable changes to this project will be documented in this file.

## [Unreleased]
- Added Gate C baseline async dispatch implementation (`/dispatch`, worker, idempotency, failover).
- Added minimal security hardening baseline (security policy, redactor, audit logger).
- Added governance guard tests and golden fixtures bound to `toolsetVersion`.
