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
### Fixed
- `evo.model.list|get` for `User`, `UserRole` and `Permissions` selected columns that do not exist in Evolution CMS 3.x (`isfrontend`, `frames`, ...) and failed with an SQL error; allowlists now match the 3.x schema.


All notable changes to this project will be documented in this file.

## [Unreleased]
- Added Gate C baseline async dispatch implementation (`/dispatch`, worker, idempotency, failover).
- Added minimal security hardening baseline (security policy, redactor, audit logger).
- Added governance guard tests and golden fixtures bound to `toolsetVersion`.
