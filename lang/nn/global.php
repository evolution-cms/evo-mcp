<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Tilgang til eMCP',
    'permission_manage' => 'Handsam MCP-tenarar',
    'permission_dispatch' => 'Køyr asynkrone MCP-oppgåver',
    'menu_tokens' => 'MCP-token',
    'errors' => [
        'forbidden' => 'Forbode',
        'scope_denied' => 'Scope avvist',
        'server_not_found' => 'Fann ikkje tenaren',
        'invalid_payload' => 'Ugyldig payload',
    ],
    'menu_settings' => 'MCP-innstillingar',
    'settings' => [
        'title' => 'MCP-innstillingar',
        'intro' => 'Desse brytarane gjeld for heile nettstaden og blir lagra i :path. Andre innstillingar (scopes, grenser, lister over tillatne) redigerer du direkte i fila.',
        'save' => 'Lagre innstillingar',
        'saved' => 'Innstillingane er lagra.',
        'error_write' => 'Klarte ikkje å lagre :path. Sjekk at webtenaren kan skrive til fila.',
        'fields' => [
            'enable' => ['label' => 'Slå på eMCP', 'hint' => 'Hovudbrytar for alle MCP-endepunkt.'],
            'mode_internal' => ['label' => 'Manager-endepunkt', 'hint' => 'MCP via manager-økta.'],
            'mode_api' => ['label' => 'API-endepunkt', 'hint' => 'Endepunkt med token-autentisering for eksterne agentar.'],
            'require_scopes' => ['label' => 'Krev token-scopes', 'hint' => 'Kvart kall må vere dekt av scopane til tokenet.'],
            'enable_write_tools' => ['label' => 'Tillat skriveverktøy', 'hint' => 'Agentar kan opprette og endre dokument og element (evo.write.*). Tokenet treng òg scopet mcp:write.'],
            'self_service' => ['label' => 'Sjølvbeteningsside for token', 'hint' => 'Brukarar lagar sine eigne token i manageren.'],
            'rate_limit' => ['label' => 'Avgrensing av førespurnader', 'hint' => 'Avgrensar talet på førespurnader per minutt for kvar brukar.'],
            'audit' => ['label' => 'Revisjonslogg', 'hint' => 'Registrer kvart MCP-kall i revisjonsloggen.'],
            'stream' => ['label' => 'Straumde svar', 'hint' => 'Tillat straumde (SSE) svar.'],
        ],
    ],
];
