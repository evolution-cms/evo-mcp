<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Acceso a eMCP',
    'permission_manage' => 'Gestionar servidores MCP',
    'permission_dispatch' => 'Ejecutar tareas MCP asíncronas',
    'menu_tokens' => 'Tokens MCP',
    'errors' => [
        'forbidden' => 'Prohibido',
        'scope_denied' => 'Scope denegado',
        'server_not_found' => 'Servidor no encontrado',
        'invalid_payload' => 'Payload no válido',
    ],
    'menu_settings' => 'Ajustes de MCP',
    'settings' => [
        'title' => 'Ajustes de MCP',
        'intro' => 'Estos interruptores se aplican a todo el sitio y se guardan en :path. Las demás opciones (scopes, límites, listas permitidas) se editan directamente en ese archivo.',
        'save' => 'Guardar ajustes',
        'saved' => 'Ajustes guardados.',
        'error_write' => 'No se pudo guardar :path. Compruebe que el servidor web puede escribir en él.',
        'fields' => [
            'enable' => ['label' => 'Activar eMCP', 'hint' => 'Interruptor general de todos los endpoints MCP.'],
            'mode_internal' => ['label' => 'Endpoint del manager', 'hint' => 'MCP a través de la sesión del manager.'],
            'mode_api' => ['label' => 'Endpoint de API', 'hint' => 'Endpoint autenticado por token para agentes externos.'],
            'require_scopes' => ['label' => 'Exigir scopes del token', 'hint' => 'Cada llamada debe estar cubierta por los scopes de su token.'],
            'enable_write_tools' => ['label' => 'Permitir herramientas de escritura', 'hint' => 'Los agentes pueden crear y modificar documentos y elementos (evo.write.*). El token también necesita el scope mcp:write.'],
            'self_service' => ['label' => 'Página de autoservicio de tokens', 'hint' => 'Los usuarios crean sus propios tokens en el manager.'],
            'rate_limit' => ['label' => 'Límite de peticiones', 'hint' => 'Limita las peticiones por minuto de cada usuario.'],
            'audit' => ['label' => 'Registro de auditoría', 'hint' => 'Registrar cada llamada MCP en el registro de auditoría.'],
            'stream' => ['label' => 'Respuestas en streaming', 'hint' => 'Permitir respuestas en streaming (SSE).'],
        ],
    ],
];
