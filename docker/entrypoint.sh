#!/usr/bin/env bash
# First start: install Evolution CMS (SQLite), install eMCP from /package, migrate, issue a token.
# Later starts: only refresh the package autoload and re-run migrations.
set -euo pipefail

SITE=/var/www/html
CORE="$SITE/core"
PACKAGE=/package
ADMIN_USER="${EVO_ADMIN_USERNAME:-admin}"
ADMIN_PASS="${EVO_ADMIN_PASSWORD:-123456}"
ADMIN_EMAIL="${EVO_ADMIN_EMAIL:-admin@example.com}"
TOKEN_FILE="$CORE/storage/emcp-token.txt"
# Optional extras to install at first start, e.g. EVO_EXTRAS="elcreator/aimage"
EXTRAS="${EVO_EXTRAS:-}"

log() { printf '\n\033[1;34m[evo-mcp]\033[0m %s\n' "$*"; }

if [ ! -f "$CORE/config/database/connections/default.php" ]; then
    log "Installing Evolution CMS (sqlite) ..."
    (cd "$SITE/install" && php cli-install.php \
        --typeInstall=1 --databaseType=sqlite --database=evolution --tablePrefix=evo_ \
        --cmsAdmin="$ADMIN_USER" --cmsAdminEmail="$ADMIN_EMAIL" --cmsPassword="$ADMIN_PASS" \
        --language=en --removeInstall=y --skipComposer=y)

    log "Wiring eMCP from $PACKAGE (path repository, symlinked) ..."
    mkdir -p "$CORE/custom"
    # composer-merge-plugin resolves the path repository relative to core/custom, hence the ../ chain to /package.
    cat > "$CORE/custom/composer.json" <<JSON
{
    "name": "evolutioncms/custom",
    "minimum-stability": "dev",
    "prefer-stable": true,
    "repositories": [
        {"type": "path", "url": "../../../../../package", "options": {"symlink": true}}
    ],
    "require": {
        "evolution-cms/emcp": "*@dev"
    },
    "autoload": {"psr-4": {}}
}
JSON
    # System composer, not core/vendor/bin/composer: the latter would replace the very files it runs from.
    (cd "$CORE" && composer update --no-dev --no-interaction --optimize-autoloader --with-all-dependencies evolution-cms/emcp)
    # Evo 3.5.9+ caches the configuration (providers included) during install; drop it so eMCP loads.
    rm -f "$CORE/storage/cache/env.php"

    log "Publishing eMCP config ..."
    (cd "$CORE" \
        && php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-config --force \
        && php artisan vendor:publish --provider="EvolutionCMS\\eMCP\\eMCPServiceProvider" --tag=emcp-mcp-config --force)

    # The CLI installer leaves default_template pointing at a template it did not create.
    (cd "$CORE" && php -r '$p=new PDO("sqlite:database/evolution.sqlite");$p->exec("update evo_system_settings set setting_value=(select min(id) from evo_site_templates) where setting_name=\"default_template\"");')

    if [ -n "$EXTRAS" ]; then
        log "Installing extras: $EXTRAS ..."
        for pkg in $(echo "$EXTRAS" | tr ',' ' '); do
            (cd "$CORE" && php -r '$p="custom/composer.json";$d=json_decode(file_get_contents($p),true);$d["require"][$argv[1]]="*";file_put_contents($p,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' "$pkg")
        done
        (cd "$CORE" && composer update --no-dev --no-interaction --optimize-autoloader --with-all-dependencies $EXTRAS             && php artisan package:discover >/dev/null && php artisan migrate --force)
    fi

    # Test site: write tools on, so the token below can exercise the whole toolset.
    sed -i "s/'enable_write_tools' => false/'enable_write_tools' => true/" "$CORE/custom/config/cms/settings/eMCP.php"
fi

log "Running migrations ..."
(cd "$CORE" && composer dump-autoload -o -q && php artisan migrate --force && php artisan cache:clear-full >/dev/null 2>&1 || true)

if [ ! -f "$TOKEN_FILE" ]; then
    log "Issuing an MCP token for '$ADMIN_USER' ..."
    (cd "$CORE" && php artisan emcp:token:create "$ADMIN_USER" --name="docker" --scopes=mcp:read,mcp:call,mcp:write --expires=never --json) \
        | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["token"] ?? "";' > "$TOKEN_FILE"
fi

chown -R www-data:www-data "$SITE/assets" "$CORE/storage" "$CORE/database" "$CORE/custom" "$CORE/config" 2>/dev/null || true

TOKEN="$(cat "$TOKEN_FILE")"
PORT="${EVO_PUBLIC_PORT:-8080}"
cat <<EOF

==================================================================
 Evolution CMS + eMCP is ready.

   Site      http://localhost:$PORT/
   Manager   http://localhost:$PORT/manager/   ($ADMIN_USER / $ADMIN_PASS)
   Tokens UI http://localhost:$PORT/manager/emcp/tokens
   Settings  http://localhost:$PORT/manager/emcp/settings
   MCP       http://localhost:$PORT/mcp/content

   Token ($ADMIN_USER, read+call+write, never expires):
   $TOKEN

   claude mcp add --transport http evo http://localhost:$PORT/mcp/content \\
       --header "Authorization: Bearer $TOKEN"
==================================================================

EOF

# Background workers (sTask, aIMage jobs, ...) need the scheduler; harmless when nothing is queued.
if [ "${EVO_SCHEDULER:-1}" = "1" ]; then
    (cd "$CORE" && while true; do su -s /bin/sh www-data -c "php artisan schedule:run" >/dev/null 2>&1; sleep 60; done) &
fi

exec "$@"
