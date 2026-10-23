<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Zugriff auf eMCP',
    'permission_manage' => 'MCP-Server verwalten',
    'permission_dispatch' => 'Asynchrone MCP-Aufgaben ausführen',
    'menu_tokens' => 'MCP-Tokens',
    'errors' => [
        'forbidden' => 'Zugriff verweigert',
        'scope_denied' => 'Scope verweigert',
        'server_not_found' => 'Server nicht gefunden',
        'invalid_payload' => 'Ungültiger Payload',
    ],
    'menu_settings' => 'MCP-Einstellungen',
    'settings' => [
        'title' => 'MCP-Einstellungen',
        'intro' => 'Diese Schalter gelten für die gesamte Website und werden in :path gespeichert. Weitere Optionen (Scopes, Limits, Allowlists) werden direkt in dieser Datei bearbeitet.',
        'save' => 'Einstellungen speichern',
        'saved' => 'Einstellungen gespeichert.',
        'error_write' => ':path konnte nicht gespeichert werden. Prüfen Sie, ob der Webserver in die Datei schreiben darf.',
        'fields' => [
            'enable' => ['label' => 'eMCP aktivieren', 'hint' => 'Hauptschalter für alle MCP-Endpunkte.'],
            'mode_internal' => ['label' => 'Manager-Endpunkt', 'hint' => 'MCP über die Manager-Sitzung.'],
            'mode_api' => ['label' => 'API-Endpunkt', 'hint' => 'Per Token authentifizierter Endpunkt für externe Agenten.'],
            'require_scopes' => ['label' => 'Token-Scopes erzwingen', 'hint' => 'Jeder Aufruf muss von den Scopes seines Tokens abgedeckt sein.'],
            'enable_write_tools' => ['label' => 'Schreib-Tools erlauben', 'hint' => 'Agenten dürfen Dokumente und Elemente anlegen und ändern (evo.write.*). Das Token braucht zusätzlich den Scope mcp:write.'],
            'enable_destructive_tools' => ['label' => 'Destruktive Tools erlauben', 'hint' => 'Agenten dürfen Erweiterungs-Tools aufrufen, die Daten löschen. Setzt auch die Schreib-Tools voraus; das Token braucht zusätzlich den Scope mcp:destructive.'],
            'enable_code_tools' => ['label' => 'Schreiben von Backend-Code erlauben', 'hint' => 'Agenten dürfen Snippets, Plugins und TV-Bindungen speichern, die Code auf dem Server ausführen (@EVAL, @SELECT, @INCLUDE). Setzt auch die Schreib-Tools voraus; das Token braucht zusätzlich den Scope mcp:code und sein Besitzer eine Berechtigung zum Bearbeiten von Snippets, Plugins oder Modulen.'],
            'self_service' => ['label' => 'Self-Service-Seite für Tokens', 'hint' => 'Benutzer erstellen ihre eigenen Tokens im Manager.'],
            'rate_limit' => ['label' => 'Ratenbegrenzung', 'hint' => 'Begrenzt die Anfragen pro Minute für jeden Benutzer.'],
            'audit' => ['label' => 'Audit-Log', 'hint' => 'Jeden MCP-Aufruf im Audit-Log protokollieren.'],
            'stream' => ['label' => 'Gestreamte Antworten', 'hint' => 'Gestreamte (SSE) Antworten erlauben.'],
        ],
    ],
];
