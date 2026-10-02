<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Přístup k eMCP',
    'permission_manage' => 'Správa MCP serverů',
    'permission_dispatch' => 'Asynchronní spouštění MCP úloh',
    'menu_tokens' => 'MCP tokeny',
    'errors' => [
        'forbidden' => 'Zakázáno',
        'scope_denied' => 'Nedostatečný scope',
        'server_not_found' => 'Server nenalezen',
        'invalid_payload' => 'Neplatný payload',
    ],
    'menu_settings' => 'Nastavení MCP',
    'settings' => [
        'title' => 'Nastavení MCP',
        'intro' => 'Tyto přepínače platí pro celý web a ukládají se do :path. Ostatní volby (scopes, limity, seznamy povolených) se upravují přímo v tomto souboru.',
        'save' => 'Uložit nastavení',
        'saved' => 'Nastavení uloženo.',
        'error_write' => 'Soubor :path se nepodařilo uložit. Zkontrolujte, zda do něj webový server může zapisovat.',
        'fields' => [
            'enable' => ['label' => 'Zapnout eMCP', 'hint' => 'Hlavní vypínač všech MCP endpointů.'],
            'mode_internal' => ['label' => 'Endpoint v manažeru', 'hint' => 'MCP přes relaci manažera.'],
            'mode_api' => ['label' => 'API endpoint', 'hint' => 'Endpoint s ověřením tokenem pro externí agenty.'],
            'require_scopes' => ['label' => 'Vyžadovat scopes tokenu', 'hint' => 'Každé volání musí být pokryto scopes svého tokenu.'],
            'enable_write_tools' => ['label' => 'Povolit zapisovací nástroje', 'hint' => 'Agenti mohou vytvářet a měnit dokumenty a elementy (evo.write.*). Token navíc potřebuje scope mcp:write.'],
            'self_service' => ['label' => 'Samoobslužná stránka tokenů', 'hint' => 'Uživatelé si vytvářejí vlastní tokeny v manažeru.'],
            'rate_limit' => ['label' => 'Omezení počtu požadavků', 'hint' => 'Omezí počet požadavků za minutu pro každého uživatele.'],
            'audit' => ['label' => 'Auditní log', 'hint' => 'Zapisovat každé volání MCP do auditního logu.'],
            'stream' => ['label' => 'Streamované odpovědi', 'hint' => 'Povolit streamované (SSE) odpovědi.'],
        ],
    ],
];
