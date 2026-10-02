<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Toegang tot eMCP',
    'permission_manage' => 'MCP-servers beheren',
    'permission_dispatch' => 'Asynchrone MCP-taken uitvoeren',
    'menu_tokens' => 'MCP-tokens',
    'errors' => [
        'forbidden' => 'Verboden',
        'scope_denied' => 'Scope geweigerd',
        'server_not_found' => 'Server niet gevonden',
        'invalid_payload' => 'Ongeldige payload',
    ],
    'menu_settings' => 'MCP-instellingen',
    'settings' => [
        'title' => 'MCP-instellingen',
        'intro' => 'Deze schakelaars gelden voor de hele site en worden opgeslagen in :path. Overige opties (scopes, limieten, toegestane lijsten) bewerkt u rechtstreeks in dat bestand.',
        'save' => 'Instellingen opslaan',
        'saved' => 'Instellingen opgeslagen.',
        'error_write' => ':path kon niet worden opgeslagen. Controleer of de webserver naar het bestand kan schrijven.',
        'fields' => [
            'enable' => ['label' => 'eMCP inschakelen', 'hint' => 'Hoofdschakelaar voor alle MCP-endpoints.'],
            'mode_internal' => ['label' => 'Manager-endpoint', 'hint' => 'MCP via de managersessie.'],
            'mode_api' => ['label' => 'API-endpoint', 'hint' => 'Met token geauthenticeerd endpoint voor externe agents.'],
            'require_scopes' => ['label' => 'Token-scopes vereisen', 'hint' => 'Elke aanroep moet binnen de scopes van zijn token vallen.'],
            'enable_write_tools' => ['label' => 'Schrijftools toestaan', 'hint' => 'Agents mogen documenten en elementen aanmaken en wijzigen (evo.write.*). Het token heeft ook de scope mcp:write nodig.'],
            'self_service' => ['label' => 'Selfservicepagina voor tokens', 'hint' => 'Gebruikers maken hun eigen tokens aan in de manager.'],
            'rate_limit' => ['label' => 'Aanvraaglimiet', 'hint' => 'Beperkt het aantal aanvragen per minuut per gebruiker.'],
            'audit' => ['label' => 'Auditlog', 'hint' => 'Elke MCP-aanroep vastleggen in het auditlog.'],
            'stream' => ['label' => 'Gestreamde antwoorden', 'hint' => 'Gestreamde (SSE) antwoorden toestaan.'],
        ],
    ],
];
