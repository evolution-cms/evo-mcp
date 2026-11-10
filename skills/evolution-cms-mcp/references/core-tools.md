# eMCP core tools

Canonical contract: `TOOLSET.md` in the eMCP package (toolset version 1.0).
All tools are called with MCP `tools/call`: `{"name": "<tool>", "arguments": {...}}`.

## Contents

- [Content reads](#content-reads)
- [Search filters](#search-filters)
- [Content writes](#content-writes)
- [Elements](#elements)
- [Models](#models)
- [Responses](#responses)
- [Errors](#errors)

## Content reads

All return non-deleted documents the acting user may see (document groups apply).

| Tool | Required | Optional |
|---|---|---|
| `evo.content.get` | `id` | `with_tvs` |
| `evo.content.root_tree` | `limit` | `depth`, `offset`, `with_tvs` |
| `evo.content.children` | `id`, `limit` | `offset`, `with_tvs` |
| `evo.content.descendants` | `id`, `limit` | `depth`, `offset`, `with_tvs` |
| `evo.content.ancestors` | `id`, `limit` | `depth`, `offset`, `with_tvs` |
| `evo.content.siblings` | `id`, `limit` | `offset`, `with_tvs` |
| `evo.content.neighbors` | `id`, `limit` | `offset`, `with_tvs` |
| `evo.content.prev_siblings` / `next_siblings` | `id`, `limit` | `offset`, `with_tvs` |
| `evo.content.children_range` | `id`, `from`, `limit` | `to`, `offset`, `with_tvs` |
| `evo.content.siblings_range` | `id`, `from`, `limit` | `to`, `offset`, `with_tvs` |
| `evo.content.search` | `limit` | see below |

List items carry `id, parent, pagetitle, longtitle, description, alias, published, deleted,
hidemenu, menuindex, template, createdon, editedon, pub_date, unpub_date, isfolder, type` and the TVs
asked for. `evo.content.get` adds `menutitle, introtext, content, link_attributes, searchable,
cacheable, richtext, contentType, editedby, createdby, publishedon`.

`with_tvs`: TV names, `"price"` for the stored value or `"price:d"` to fall back to the TV
default when a document has no value.

Ranges (`children_range`, `siblings_range`) select by `menuindex` from `from` to `to`.

## Search filters

`evo.content.search` arguments:

- `parent` (int) with `depth` (int, max 6): restrict to a subtree.
- `published`, `deleted`, `hidemenu` (bool), `template` (int).
- `with_tvs` (string[]).
- `tv_filters`: `[{"tv": "price", "op": ">", "value": "100", "cast": "UNSIGNED", "use_default": false}]`.
- `tv_order`: `[{"tv": "price", "dir": "asc", "cast": "UNSIGNED"}]`.
- `tags_data`: `{"tv_id": 17, "tags": ["red", "sale"]}`.
- `order_by`: `id | pagetitle | menuindex | createdon | pub_date`; `order_dir`: `asc | desc`;
  `order_by_date`: `asc | desc`.
- `limit` (required), `offset`.

Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `like`, `like-l`, `like-r`, `null`,
`!null`. Casts: `UNSIGNED`, `SIGNED`, `DECIMAL(p,s)`. Raw TV filter strings are rejected.

## Content writes

Need `enable_write_tools` and the `mcp:write` scope. Each re-checks the matching manager
permission and document-group access and writes a manager log row ("... via MCP"). Document
writes do not fire `OnDocFormSave`, so plugins hooked there (search index, sitemap, SEO extras) do
not run; re-save in the manager or clear the cache when those matter.

| Tool | Arguments | Permission |
|---|---|---|
| `evo.write.content.create` | `parent` (0 = root), `fields` (needs `pagetitle`), `tvs` | `new_document`; `publish_document` to create published |
| `evo.write.content.update` | `id`, `fields`, `tvs` | `save_document`; publish changes need `publish_document` |
| `evo.write.content.publish` | `id`, `action`: `publish`, `unpublish`, `delete`, `undelete` | `publish_document` or `delete_document` |
| `evo.write.cache.clear` | none | `empty_cache` |

Writable `fields`: `pagetitle, longtitle, description, alias, introtext, content, menutitle,
template, parent, published, pub_date, unpub_date, hidemenu, menuindex, isfolder, searchable,
cacheable, richtext, type, contentType, content_dispo, link_attributes, alias_visible,
hide_from_tree, show_in_search`. `tvs` is an object keyed by TV name.

A value starting with `@EVAL` or `@SELECT` (also behind `@INHERIT`) counts as code and needs the
code gate.

## Elements

`type` is one of `template`, `tv`, `chunk`, `snippet`, `plugin`.

| Tool | Arguments | Notes |
|---|---|---|
| `evo.elements.list` | `type`, `search`, `category`, `limit` (default 50, max 200), `offset` | No code; includes `locked_by` |
| `evo.elements.get` | `type`, `id` or exact `name` | Includes code |
| `evo.write.elements.save` | `type`, `id` (omit to create), `fields` | `new_*` / `save_*` permission; refuses locked elements; fires `OnBefore*FormSave` / `On*FormSave` |

Code field per type: `content` (template), `default_text` (tv), `snippet` (chunk and snippet),
`plugincode` (plugin). Saving a snippet or plugin, or a TV with a code binding in `elements`,
`default_text` or `display_params`, needs the code gate (`enable_code_tools`, `mcp:code`).

## Models

`evo.model.list`: `model`, `limit` (required), `offset`, `order_by`, `order_dir`, `filters`:

```json
{"model": "User", "limit": 20,
 "filters": {"where": [{"field": "username", "op": "like", "value": "hanna"}]}}
```

`evo.model.get`: `model`, `id`. Only allowlisted fields are returned; `password`, `cachepwd`,
`verified_key`, `refresh_token`, `access_token` and `sessionid` are never included.

## Responses

```json
{"items": [], "meta": {"limit": 20, "offset": 0, "count": 0, "toolsetVersion": "1.0"}}
```

Get-like tools return `item` (null when not found or not visible).

## Errors

| Kind | Shape |
|---|---|
| Invalid arguments, missing permission, locked element | JSON-RPC `-32602` with a field message |
| Unknown tool or model | JSON-RPC `-32601` |
| Auth, scope, policy | HTTP `401` / `403` with `error` code |
| Duplicate `Idempotency-Key` with another payload | HTTP `409` |
| Too large | HTTP `413` (`payload_too_large`, `result_too_large`) |
| Too many requests | HTTP `429` (`rate_limited`) |
