<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Adgang til eMCP',
    'permission_manage' => 'Administrer MCP-servere',
    'permission_dispatch' => 'Afsend asynkrone MCP-opgaver',
    'menu_tokens' => 'MCP-tokens',
    'errors' => [
        'forbidden' => 'Forbudt',
        'scope_denied' => 'Scope nægtet',
        'server_not_found' => 'Server ikke fundet',
        'invalid_payload' => 'Ugyldig payload',
    ],
    'menu_settings' => 'MCP-indstillinger',
    'settings' => [
        'title' => 'MCP-indstillinger',
        'intro' => 'Disse kontakter gælder for hele sitet og gemmes i :path. Øvrige indstillinger (scopes, grænser, tilladelseslister) redigeres direkte i filen.',
        'save' => 'Gem indstillinger',
        'saved' => 'Indstillingerne er gemt.',
        'error_write' => 'Kunne ikke gemme :path. Kontrollér, at webserveren kan skrive til filen.',
        'fields' => [
            'enable' => ['label' => 'Aktivér eMCP', 'hint' => 'Hovedkontakt for alle MCP-endpoints.'],
            'mode_internal' => ['label' => 'Manager-endpoint', 'hint' => 'MCP via manager-sessionen.'],
            'mode_api' => ['label' => 'API-endpoint', 'hint' => 'Token-godkendt endpoint til eksterne agenter.'],
            'require_scopes' => ['label' => 'Kræv token-scopes', 'hint' => 'Hvert kald skal være dækket af tokenets scopes.'],
            'enable_write_tools' => ['label' => 'Tillad skriveværktøjer', 'hint' => 'Agenter må oprette og ændre dokumenter og elementer (evo.write.*). Tokenet skal også have scopet mcp:write.'],
            'self_service' => ['label' => 'Selvbetjeningsside for tokens', 'hint' => 'Brugere opretter selv deres tokens i manageren.'],
            'rate_limit' => ['label' => 'Begrænsning af forespørgsler', 'hint' => 'Begrænser antallet af forespørgsler pr. minut for hver bruger.'],
            'audit' => ['label' => 'Revisionslog', 'hint' => 'Registrér hvert MCP-kald i revisionsloggen.'],
            'stream' => ['label' => 'Streamede svar', 'hint' => 'Tillad streamede (SSE) svar.'],
        ],
    ],
];
