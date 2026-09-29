<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'eMCP-ə giriş',
    'permission_manage' => 'MCP serverlərini idarə et',
    'permission_dispatch' => 'Asinxron MCP tapşırıqlarını göndər',
    'menu_tokens' => 'MCP tokenləri',
    'errors' => [
        'forbidden' => 'Qadağandır',
        'scope_denied' => 'Əhatə dairəsi kifayət deyil',
        'server_not_found' => 'Server tapılmadı',
        'invalid_payload' => 'Yanlış payload',
    ],
    'menu_settings' => 'MCP parametrləri',
    'settings' => [
        'title' => 'MCP parametrləri',
        'intro' => 'Bu açarlar bütün sayta aiddir və :path faylında saxlanılır. Digər parametrlər (scope-lar, limitlər, icazə siyahıları) birbaşa həmin faylda redaktə olunur.',
        'save' => 'Parametrləri saxla',
        'saved' => 'Parametrlər saxlanıldı.',
        'error_write' => ':path saxlanıla bilmədi. Veb serverin bu fayla yaza bildiyini yoxlayın.',
        'fields' => [
            'enable' => ['label' => 'eMCP-ni aktivləşdir', 'hint' => 'Bütün MCP endpoint-ləri üçün əsas açar.'],
            'mode_internal' => ['label' => 'Manager endpoint-i', 'hint' => 'Manager sessiyası üzərindən MCP.'],
            'mode_api' => ['label' => 'API endpoint-i', 'hint' => 'Xarici agentlər üçün tokenlə autentifikasiya olunan endpoint.'],
            'require_scopes' => ['label' => 'Token scope-larını tələb et', 'hint' => 'Hər çağırış tokenin scope-ları ilə əhatə olunmalıdır.'],
            'enable_write_tools' => ['label' => 'Yazma alətlərinə icazə ver', 'hint' => 'Agentlər sənədləri və elementləri yarada və dəyişə bilər (evo.write.*). Tokenə həmçinin mcp:write scope-u lazımdır.'],
            'self_service' => ['label' => 'Tokenlərin özünəxidmət səhifəsi', 'hint' => 'İstifadəçilər öz tokenlərini managerdə yaradır.'],
            'rate_limit' => ['label' => 'Sorğu limiti', 'hint' => 'Hər istifadəçi üçün dəqiqədəki sorğu sayını məhdudlaşdırır.'],
            'audit' => ['label' => 'Audit jurnalı', 'hint' => 'Hər MCP çağırışını audit jurnalına yazır.'],
            'stream' => ['label' => 'Axınlı cavablar', 'hint' => 'Axınlı (SSE) cavablara icazə verir.'],
        ],
    ],
];
