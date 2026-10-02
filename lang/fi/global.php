<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Pääsy eMCP:hen',
    'permission_manage' => 'Hallitse MCP-palvelimia',
    'permission_dispatch' => 'Suorita asynkronisia MCP-tehtäviä',
    'menu_tokens' => 'MCP-tunnisteet',
    'errors' => [
        'forbidden' => 'Kielletty',
        'scope_denied' => 'Scope evätty',
        'server_not_found' => 'Palvelinta ei löytynyt',
        'invalid_payload' => 'Virheellinen payload',
    ],
    'menu_settings' => 'MCP-asetukset',
    'settings' => [
        'title' => 'MCP-asetukset',
        'intro' => 'Nämä kytkimet koskevat koko sivustoa ja tallennetaan tiedostoon :path. Muita asetuksia (scopet, rajat, sallittujen listat) muokataan suoraan tiedostossa.',
        'save' => 'Tallenna asetukset',
        'saved' => 'Asetukset tallennettu.',
        'error_write' => 'Tiedostoa :path ei voitu tallentaa. Tarkista, että verkkopalvelin voi kirjoittaa siihen.',
        'fields' => [
            'enable' => ['label' => 'Ota eMCP käyttöön', 'hint' => 'Pääkytkin kaikille MCP-endpointeille.'],
            'mode_internal' => ['label' => 'Managerin endpoint', 'hint' => 'MCP managerin istunnon kautta.'],
            'mode_api' => ['label' => 'API-endpoint', 'hint' => 'Tokenilla todennettu endpoint ulkoisille agenteille.'],
            'require_scopes' => ['label' => 'Vaadi tokenin scopet', 'hint' => 'Jokaisen kutsun on kuuluttava tokenin scopeihin.'],
            'enable_write_tools' => ['label' => 'Salli kirjoitustyökalut', 'hint' => 'Agentit voivat luoda ja muuttaa dokumentteja ja elementtejä (evo.write.*). Token tarvitsee lisäksi scopen mcp:write.'],
            'self_service' => ['label' => 'Tokenien itsepalvelusivu', 'hint' => 'Käyttäjät luovat omat tokeninsa managerissa.'],
            'rate_limit' => ['label' => 'Pyyntörajoitus', 'hint' => 'Rajoittaa kunkin käyttäjän pyyntöjä minuutissa.'],
            'audit' => ['label' => 'Auditointiloki', 'hint' => 'Kirjaa jokainen MCP-kutsu auditointilokiin.'],
            'stream' => ['label' => 'Suoratoistetut vastaukset', 'hint' => 'Salli suoratoistetut (SSE) vastaukset.'],
        ],
    ],
];
