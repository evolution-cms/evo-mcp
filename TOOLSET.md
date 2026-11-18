# TOOLSET — eMCP Canonical Tool Contract v2

## 1. Scope
This file defines the public contract for eMCP domain tools.

Contract id:
- `platform`: `eMCP`
- `toolsetVersion`: `2.0`

Normative relation:
- `SPEC.md` defines platform/runtime rules.
- `TOOLSET.md` defines public tool names, params, outputs, and error cases.

## 2. Stability Policy
- `evo.content.*` and `evo.model.*` are stable public namespaces.
- Rename/removal is allowed only in `MAJOR` release.
- New optional params/tools may be added in `MINOR`.
- Existing optional params cannot become required in `MINOR`.
- Breaking schema/error changes require `MAJOR` after deprecation notice.

## 3. Capability Metadata
`initialize` response MUST include:
- `serverInfo.platform = "eMCP"`
- `serverInfo.platformVersion = "<package-version>"`
- `capabilities.evo.toolsetVersion = "2.0"`

This platform metadata is part of the public contract.
Absence or incompatible change of this metadata is a breaking change (`MAJOR`).

## 4. Canonical Tool Names (v2)

### 4.1 `evo.content.*`
- `evo.content.search`
- `evo.content.get`
- `evo.content.tree`
- `evo.content.ancestors`
- `evo.content.siblings`

### 4.2 `evo.model.*`
- `evo.model.list`
- `evo.model.get`

### 4.3 `evo.elements.*`
- `evo.elements.list`
- `evo.elements.get`

### 4.4 `evo.write.*`
- `evo.write.content.update`
- `evo.write.content.create`
- `evo.write.content.publish`
- `evo.write.elements.save`
- `evo.write.cache.clear`

## 5. Common Tool Call Envelope
MCP `tools/call` payload:

```json
{
  "name": "evo.content.search",
  "arguments": {
    "limit": 20,
    "offset": 0
  }
}
```

## 6. Params Contracts

### 6.1 `evo.content.search`
Arguments:
- `parent` int optional
- `published` bool optional
- `deleted` bool optional
- `template` int optional
- `hidemenu` bool optional
- `depth` int optional
- `with_tvs` string[] optional (`name` or `name:d`)
- `tv_filters` object[] optional:
- `{ "tv": "price", "op": ">", "value": "100", "cast": "UNSIGNED", "use_default": false }`
- `tv_order` object[] optional:
- `{ "tv": "price", "dir": "asc", "cast": "UNSIGNED", "use_default": false }`
- `tags_data` object optional: `{ "tv_id": 17, "tags": ["a", "b"] }`
- `order_by` string optional (`id|pagetitle|menuindex|createdon|pub_date`)
- `order_dir` string optional (`asc|desc`)
- `order_by_date` string optional (`asc|desc`)
- `limit` int required
- `offset` int optional

Constraints:
- operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `like`, `like-l`, `like-r`, `null`, `!null`
- casts: `UNSIGNED`, `SIGNED`, `DECIMAL(p,s)`
- raw TV DSL is forbidden
- pagination is required

### 6.2 `evo.content.get`
Arguments:
- `id` int required
- `with_tvs` string[] optional

### 6.3 `evo.content.tree`
- `id` required, with 0 representing the virtual site root.
- `depth` defaults to 1. The configured depth cap allows one extra level from the virtual root.
- `menuindex_from` and `menuindex_to` are optional inclusive bounds available at depth 1.
- `with_tvs`, required `limit` and optional `offset` apply to a flat list sorted by parent, menuindex and ID.

### 6.4 `evo.content.siblings` and `evo.content.ancestors`
- Siblings requires a positive `id`; `direction` is `all` (default), `before`, `after` or `adjacent`.
- Before and after are nearest first by the total order (menuindex, ID); adjacent returns at most the nearest visible record on each side, previous first.
- Only all accepts inclusive `menuindex_from` and `menuindex_to`. All, before and after require `limit` and accept `offset`; adjacent accepts neither.
- Ancestors requires a positive `id` and accepts optional `depth`. Omit depth for the complete visible path up to the server result limit. The result is ordered farthest to nearest. A server-limited path keeps the nearest ancestors and sets `meta.truncated`.
- All five content tools accept `with_tvs` as a list of TV names. Use the suffix `:d` to request the default when no value is stored.
- A missing, trashed or inaccessible anchor gives an empty list for tree, siblings and ancestors.

### 6.5 `evo.model.list`
Arguments:
- `model` string required (must be in allowlist)
- `filters` object optional (structured only):
- `{ "where": [ { "field": "name", "op": "like", "value": "foo" } ] }`
- `order_by` string optional
- `order_dir` string optional (`asc|desc`)
- `limit` int required
- `offset` int optional

Constraints:
- `filters.where[].field` MUST be allowlisted for selected model
- allowed operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `like`, `like-l`, `like-r`, `null`, `!null`
- raw SQL fragments or raw query DSL are forbidden

### 6.6 `evo.model.get`
Arguments:
- `model` string required (must be in allowlist)
- `id` int required

### 6.7 Elements and write tools
Element reads use the edit permission for the selected type. List returns names and metadata; get also returns the code field and honors ID before exact name.
Element saves use the corresponding new or save permission and the server's code-write policy.

Document update requires save_document. An actual change to published, pub_date or unpub_date also requires publish_document. Dates are non-negative integer timestamps; zero clears a date. Rejected publication changes and failures within the edit transaction leave both document fields and TVs unchanged. TV names must belong to the destination template. The result reports requested field and TV names whose stored values changed.

## 7. Response Contract
Tool responses SHOULD return:
- `items` array for list-like tools
- `item` object for get-like tools
- `meta` object with pagination and limits

Example:

```json
{
  "items": [],
  "meta": {
    "limit": 20,
    "offset": 0,
    "count": 0,
    "toolsetVersion": "2.0"
  }
}
```

Sensitive fields MUST never be returned:
- `password`
- `cachepwd`
- `verified_key`
- `refresh_token`
- `access_token`
- `sessionid`

## 8. Error Cases
Transport/auth/middleware errors use HTTP and non-JSON-RPC error body.
JSON-RPC dispatch errors use JSON-RPC error codes.

Canonical mapping:
- invalid params -> JSON-RPC `-32602`
- tool/model/server not found -> JSON-RPC `-32601`
- forbidden -> HTTP `403`
- unauthenticated -> HTTP `401`
- idempotency conflict (same key, different payload) -> HTTP `409`
- payload/response over configured limits -> HTTP `413`

## 9. Limits and Pagination
- large responses MUST be paginated
- `limit` cannot exceed configured `max_result_items`
- serialized result cannot exceed configured `max_result_bytes`

## Migration from toolset 1.0

Deploy both packages together. Toolset 2.0 requires DocumentManager 1.1.0 for atomic document and TV updates.
The names below are relative to the `evo.content.` prefix.

| v1 call | v2 call |
| --- | --- |
| `root_tree(depth=d)` | `tree(id=0, depth=d+1)`; use depth 2 for the old default. |
| `children(id)` | `tree(id, depth=1)` |
| `descendants(id, depth=d)` | `tree(id, depth=d)`; use depth 1 for omitted or null depth. |
| `children_range(id, from, to)` | `tree(id, depth=1, menuindex_from=from, menuindex_to=to)` |
| `siblings(id)` | `siblings(id, direction=all)` |
| `prev_siblings(id)` | `siblings(id, direction=before)` |
| `next_siblings(id)` | `siblings(id, direction=after)` |
| `neighbors(id)` | `siblings(id, direction=adjacent)`; omit limit and offset. |
| `siblings_range(id, from, to)` | `siblings(id, direction=all, menuindex_from=from, menuindex_to=to)` |
| `ancestors(id, depth, limit, offset)` | `ancestors(id, depth)`; slice client-side if needed and honor meta.truncated. |
| `search(...)`, `get(...)` | Same names and arguments, now with the supported TV and search options in the schema. |

- Carry over `with_tvs` and `limit`/`offset` where supported. Restart root-tree paging at offset 0 because v1 sorted only by ID.
- Sibling directions compare `(menuindex, id)`. Equal menu positions are ordered by ID. Adjacent finds the closest accessible record on either side even across position gaps; v1 neighbors used exact positions ±1. Restart paging when equal positions could change the result set.
- Model, element and write names are unchanged. For document updates, a forbidden publication change now rejects the whole request, and the returned change lists describe persisted changes. Send TVs by name on the target template.
- Refresh the client’s tool discovery after switching versions. A client that still needs the v1 semantics should finish its migration against the v1 release before the server is upgraded.
