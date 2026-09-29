<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Accesso a eMCP',
    'permission_manage' => 'Gestisci server MCP',
    'permission_dispatch' => 'Esegui attività MCP asincrone',
    'menu_tokens' => 'Token MCP',
    'errors' => [
        'forbidden' => 'Vietato',
        'scope_denied' => 'Scope negato',
        'server_not_found' => 'Server non trovato',
        'invalid_payload' => 'Payload non valido',
    ],
    'menu_settings' => 'Impostazioni MCP',
    'settings' => [
        'title' => 'Impostazioni MCP',
        'intro' => 'Questi interruttori valgono per tutto il sito e vengono salvati in :path. Le altre opzioni (scope, limiti, liste consentite) si modificano direttamente in quel file.',
        'save' => 'Salva impostazioni',
        'saved' => 'Impostazioni salvate.',
        'error_write' => 'Impossibile salvare :path. Verificare che il server web possa scrivere nel file.',
        'fields' => [
            'enable' => ['label' => 'Attiva eMCP', 'hint' => 'Interruttore generale di tutti gli endpoint MCP.'],
            'mode_internal' => ['label' => 'Endpoint del manager', 'hint' => 'MCP tramite la sessione del manager.'],
            'mode_api' => ['label' => 'Endpoint API', 'hint' => 'Endpoint autenticato con token per agenti esterni.'],
            'require_scopes' => ['label' => 'Richiedi gli scope del token', 'hint' => 'Ogni chiamata deve rientrare negli scope del proprio token.'],
            'enable_write_tools' => ['label' => 'Consenti strumenti di scrittura', 'hint' => 'Gli agenti possono creare e modificare documenti ed elementi (evo.write.*). Il token deve avere anche lo scope mcp:write.'],
            'self_service' => ['label' => 'Pagina self-service dei token', 'hint' => 'Gli utenti creano i propri token nel manager.'],
            'rate_limit' => ['label' => 'Limite di richieste', 'hint' => 'Limita il numero di richieste al minuto per ogni utente.'],
            'audit' => ['label' => 'Registro di audit', 'hint' => 'Registra ogni chiamata MCP nel registro di audit.'],
            'stream' => ['label' => 'Risposte in streaming', 'hint' => 'Consenti risposte in streaming (SSE).'],
        ],
    ],
];
