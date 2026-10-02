<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Åtkomst till eMCP',
    'permission_manage' => 'Hantera MCP-servrar',
    'permission_dispatch' => 'Kör asynkrona MCP-uppgifter',
    'menu_tokens' => 'MCP-token',
    'errors' => [
        'forbidden' => 'Förbjudet',
        'scope_denied' => 'Scope nekad',
        'server_not_found' => 'Servern hittades inte',
        'invalid_payload' => 'Ogiltig payload',
    ],
    'menu_settings' => 'MCP-inställningar',
    'settings' => [
        'title' => 'MCP-inställningar',
        'intro' => 'Dessa reglage gäller hela webbplatsen och sparas i :path. Övriga inställningar (scopes, gränser, tillåtelselistor) redigeras direkt i filen.',
        'save' => 'Spara inställningar',
        'saved' => 'Inställningarna har sparats.',
        'error_write' => 'Det gick inte att spara :path. Kontrollera att webbservern kan skriva till filen.',
        'fields' => [
            'enable' => ['label' => 'Aktivera eMCP', 'hint' => 'Huvudreglage för alla MCP-endpoints.'],
            'mode_internal' => ['label' => 'Manager-endpoint', 'hint' => 'MCP via managerns session.'],
            'mode_api' => ['label' => 'API-endpoint', 'hint' => 'Tokenautentiserad endpoint för externa agenter.'],
            'require_scopes' => ['label' => 'Kräv token-scopes', 'hint' => 'Varje anrop måste täckas av tokenets scopes.'],
            'enable_write_tools' => ['label' => 'Tillåt skrivverktyg', 'hint' => 'Agenter får skapa och ändra dokument och element (evo.write.*). Tokenet behöver även scopet mcp:write.'],
            'self_service' => ['label' => 'Självbetjäningssida för tokens', 'hint' => 'Användare skapar egna tokens i managern.'],
            'rate_limit' => ['label' => 'Begränsning av förfrågningar', 'hint' => 'Begränsar antalet förfrågningar per minut för varje användare.'],
            'audit' => ['label' => 'Granskningslogg', 'hint' => 'Logga varje MCP-anrop i granskningsloggen.'],
            'stream' => ['label' => 'Strömmade svar', 'hint' => 'Tillåt strömmade (SSE) svar.'],
        ],
    ],
];
