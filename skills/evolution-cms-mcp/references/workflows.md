# Workflows

Multi-step recipes. Each assumes the tools exist in `tools/list` and the gates in SKILL.md
Prerequisites are open for any write step. Recipes for `aimage.*`, `amount.*` and
`adataforseo.*` live in the `aimage`, `amount` and `adataforseo` skills.

## Contents

- [Map an unknown site](#map-an-unknown-site)
- [SEO audit of a section](#seo-audit-of-a-section)
- [Bulk TV update](#bulk-tv-update)
- [Restructure the menu](#restructure-the-menu)
- [Fix a template or chunk](#fix-a-template-or-chunk)
- [Audit access](#audit-access)
- [Long calls through sTask](#long-calls-through-stask)

## Map an unknown site

1. `evo.content.root_tree` `{"depth": 2, "limit": 100}`.
2. `evo.elements.list` `{"type": "template"}` and `{"type": "tv"}` to learn which templates and
   TVs exist; note TV names for later `with_tvs`.
3. For a section, `evo.content.children` `{"id": <section>, "limit": 100}`.
4. Summarise the tree (ids, titles, templates) before proposing any change.

## SEO audit of a section

When `adataforseo.*` tools are available, follow the `adataforseo` skill for search
findings and recorded fixes, including missing-page drafts, background checks and undo.

1. `evo.content.search` `{"parent": <id>, "depth": 6, "published": true, "limit": 100}`; page with `offset`.
2. Flag items with empty `longtitle` or `description`, duplicate `alias`, or titles over 60 chars.
3. For pages needing body checks, `evo.content.get` one by one.
4. Offer fixes as a table; on approval apply with `evo.write.content.update` (`fields.longtitle`,
   `fields.description`) in batches of at most 20, then `evo.write.cache.clear`.

## Bulk TV update

1. Select targets with `evo.content.search` + `tv_filters` (for example `price` `null`).
2. Show the list and the new values; wait for approval.
3. `evo.write.content.update` `{"id": N, "tvs": {"price": "..."}}` per document; record old
   values first so they can be restored.
4. Report successes and failures separately, then clear the cache.

## Restructure the menu

1. `evo.content.children` for the parent to get current `menuindex` values.
2. Propose the new order; on approval update `fields.menuindex` (and `fields.parent` to move a
   document; `parent` 0 is the root).
3. Verify with `evo.content.children` again.

## Fix a template or chunk

1. `evo.elements.list` `{"type": "chunk", "search": "<name>"}`; stop if `locked_by` is set.
2. `evo.elements.get` to read the code; keep a copy in the conversation.
3. Show a unified diff; on approval `evo.write.elements.save` with the full new code field.
4. `evo.write.cache.clear`, then check the page with `evo.content.get` or by asking the user.

Snippets and plugins are PHP: saving them needs the code gate and the user's explicit request.

## Audit access

- Roles: `evo.model.list` `{"model": "UserRole", "limit": 50}`.
- Permissions of a role: `evo.model.list` `{"model": "RolePermissions", "limit": 100, "filters": {"where": [{"field": "role_id", "op": "=", "value": 2}]}}`.
- Users: `evo.model.list` `{"model": "User", "limit": 50}`; `UserAttribute` (joined by
  `internalKey`) for full name, e-mail, block state and login counts.

Read-only; eMCP has no tool that changes users or roles.

## Long calls through sTask

For a tool call that may exceed the HTTP timeout, POST the same JSON-RPC body to
`/mcp/{server}/dispatch` (or `/{manager_prefix}/{server}/dispatch` in the manager) with an
`Idempotency-Key` header. eMCP queues it on the `emcp_dispatch` sTask worker and returns a task
reference; repeating the same key with the same payload returns the stored result, with a
different payload `409`. Requires `queue.driver = stask` and a running sTask worker
(`php artisan emcp:sync-workers` registers it).
