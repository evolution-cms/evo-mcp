# Reusable CI for Evolution CMS Extras

The workflow `.github/workflows/extra-integration.yml` is reusable from third-party Extra repositories. It checks out the calling repository and eMCP, installs both as local Composer path repositories in a fresh Evolution CMS, runs migrations, and verifies MCP `initialize` and `tools/list` using `emcp:test`.

Add a job to an Extra repository's GitHub Actions workflow:

```yaml
jobs:
  emcp:
    uses: evolution-cms/evo-mcp/.github/workflows/extra-integration.yml@main
    with:
      evo_ref: 3.5.x
      php_version: '8.4'
      server_handle: content
      # Optional: tools your Extra contributes; each must appear in tools/list.
      expected_tools: >-
        vendor.tool_one
        vendor.tool_two
```

Inputs (all optional): `evo_ref` (Evolution CMS branch or tag, default `3.5.x`), `emcp_ref` (eMCP ref, default `main`), `php_version` (default `8.4`), `server_handle` (default `content`), `expected_tools` (space- or newline-separated tool names, default empty).

The job, in order: installs Evolution CMS dependencies, installs the Extra and eMCP from the checked-out sources, installs the CMS on SQLite, runs `package:discover`, publishes the eMCP config and runs all migrations, shows both packages and the configured servers, then runs `emcp:test`. The run summary contains the `emcp:test` output, including every tool name `tools/list` returned (all pages).

The caller must have a valid root `composer.json` declaring the Extra's Composer package name. The workflow tests the calling commit rather than a published Packagist version. It does not require a Claude Code plugin, and it does not install optional API credentials.

`emcp:test` verifies MCP `initialize` and `tools/list` for the selected server, and with `expected_tools` it fails when any named tool is missing (`php artisan emcp:test --server=content --expect-tool=vendor.tool_one`). It does not call the tools: invoking them needs a manager identity and data the Extra must set up, so `tools/call` coverage remains the Extra's own test suite. Without `expected_tools` the workflow does not assert that the Extra contributes any tool.

Pin the reusable workflow to a release tag or commit SHA for reproducible CI once a stable version is available.
