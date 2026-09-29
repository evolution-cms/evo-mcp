<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Dostęp do eMCP',
    'permission_manage' => 'Zarządzaj serwerami MCP',
    'permission_dispatch' => 'Uruchamiaj asynchroniczne zadania MCP',
    'menu_tokens' => 'Tokeny MCP',
    'errors' => [
        'forbidden' => 'Zabronione',
        'scope_denied' => 'Odmowa scope',
        'server_not_found' => 'Nie znaleziono serwera',
        'invalid_payload' => 'Nieprawidłowy payload',
    ],
    'menu_settings' => 'Ustawienia MCP',
    'settings' => [
        'title' => 'Ustawienia MCP',
        'intro' => 'Te przełączniki dotyczą całej witryny i są zapisywane w :path. Pozostałe opcje (scopes, limity, listy dozwolonych) edytuje się bezpośrednio w tym pliku.',
        'save' => 'Zapisz ustawienia',
        'saved' => 'Ustawienia zapisane.',
        'error_write' => 'Nie udało się zapisać :path. Sprawdź, czy serwer WWW może zapisywać do tego pliku.',
        'fields' => [
            'enable' => ['label' => 'Włącz eMCP', 'hint' => 'Główny przełącznik wszystkich endpointów MCP.'],
            'mode_internal' => ['label' => 'Endpoint menedżera', 'hint' => 'MCP przez sesję menedżera.'],
            'mode_api' => ['label' => 'Endpoint API', 'hint' => 'Endpoint uwierzytelniany tokenem dla zewnętrznych agentów.'],
            'require_scopes' => ['label' => 'Wymagaj scopes tokenu', 'hint' => 'Każde wywołanie musi mieścić się w scopes swojego tokenu.'],
            'enable_write_tools' => ['label' => 'Zezwól na narzędzia zapisu', 'hint' => 'Agenci mogą tworzyć i zmieniać dokumenty oraz elementy (evo.write.*). Token potrzebuje też scope mcp:write.'],
            'self_service' => ['label' => 'Strona samoobsługi tokenów', 'hint' => 'Użytkownicy tworzą własne tokeny w menedżerze.'],
            'rate_limit' => ['label' => 'Limit żądań', 'hint' => 'Ogranicza liczbę żądań na minutę dla każdego użytkownika.'],
            'audit' => ['label' => 'Dziennik audytu', 'hint' => 'Zapisuj każde wywołanie MCP w dzienniku audytu.'],
            'stream' => ['label' => 'Odpowiedzi strumieniowe', 'hint' => 'Zezwól na odpowiedzi strumieniowe (SSE).'],
        ],
    ],
];
