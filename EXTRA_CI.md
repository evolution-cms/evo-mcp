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
```

The caller must have a valid root `composer.json` declaring the Extra's Composer package name. The workflow tests the calling commit rather than a published Packagist version. It does not require a Claude Code plugin, and it does not install optional API credentials.

`emcp:test` verifies MCP protocol behavior for the selected server. A package-specific test asserting that particular tools are present in `tools/list` remains the responsibility of the Extra. This workflow does not assert that every Extra contributes a tool.

Pin the reusable workflow to a release tag or commit SHA for reproducible CI once a stable version is available.
