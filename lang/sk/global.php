<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Prístup k eMCP',
    'permission_manage' => 'Správa MCP serverov',
    'permission_dispatch' => 'Asynchrónne spúšťanie MCP úloh',
    'menu_tokens' => 'MCP tokeny',
    'errors' => [
        'forbidden' => 'Zakázané',
        'scope_denied' => 'Nedostatočný scope',
        'server_not_found' => 'Server sa nenašiel',
        'invalid_payload' => 'Neplatný payload',
    ],
    'menu_settings' => 'Nastavenia MCP',
    'settings' => [
        'title' => 'Nastavenia MCP',
        'intro' => 'Tieto prepínače platia pre celý web a ukladajú sa do :path. Ostatné voľby (scopes, limity, zoznamy povolených) sa upravujú priamo v tomto súbore.',
        'save' => 'Uložiť nastavenia',
        'saved' => 'Nastavenia uložené.',
        'error_write' => 'Súbor :path sa nepodarilo uložiť. Skontrolujte, či doň webový server môže zapisovať.',
        'fields' => [
            'enable' => ['label' => 'Zapnúť eMCP', 'hint' => 'Hlavný vypínač všetkých MCP endpointov.'],
            'mode_internal' => ['label' => 'Endpoint v manažéri', 'hint' => 'MCP cez reláciu manažéra.'],
            'mode_api' => ['label' => 'API endpoint', 'hint' => 'Endpoint s overením tokenom pre externých agentov.'],
            'require_scopes' => ['label' => 'Vyžadovať scopes tokenu', 'hint' => 'Každé volanie musí byť pokryté scopes svojho tokenu.'],
            'enable_write_tools' => ['label' => 'Povoliť zapisovacie nástroje', 'hint' => 'Agenti môžu vytvárať a meniť dokumenty a elementy (evo.write.*). Token potrebuje aj scope mcp:write.'],
            'self_service' => ['label' => 'Samoobslužná stránka tokenov', 'hint' => 'Používatelia si vytvárajú vlastné tokeny v manažéri.'],
            'rate_limit' => ['label' => 'Obmedzenie počtu požiadaviek', 'hint' => 'Obmedzí počet požiadaviek za minútu pre každého používateľa.'],
            'audit' => ['label' => 'Auditný log', 'hint' => 'Zapisovať každé volanie MCP do auditného logu.'],
            'stream' => ['label' => 'Streamované odpovede', 'hint' => 'Povoliť streamované (SSE) odpovede.'],
        ],
    ],
];
