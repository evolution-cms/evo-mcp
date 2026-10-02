<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Acesso ao eMCP',
    'permission_manage' => 'Gerenciar servidores MCP',
    'permission_dispatch' => 'Executar tarefas MCP assíncronas',
    'menu_tokens' => 'Tokens MCP',
    'errors' => [
        'forbidden' => 'Proibido',
        'scope_denied' => 'Scope negado',
        'server_not_found' => 'Servidor não encontrado',
        'invalid_payload' => 'Payload inválido',
    ],
    'menu_settings' => 'Configurações do MCP',
    'settings' => [
        'title' => 'Configurações do MCP',
        'intro' => 'Estes interruptores valem para todo o site e são salvos em :path. As demais opções (scopes, limites, listas permitidas) são editadas diretamente nesse arquivo.',
        'save' => 'Salvar configurações',
        'saved' => 'Configurações salvas.',
        'error_write' => 'Não foi possível salvar :path. Verifique se o servidor web pode gravar nesse arquivo.',
        'fields' => [
            'enable' => ['label' => 'Ativar eMCP', 'hint' => 'Interruptor geral de todos os endpoints MCP.'],
            'mode_internal' => ['label' => 'Endpoint do manager', 'hint' => 'MCP pela sessão do manager.'],
            'mode_api' => ['label' => 'Endpoint de API', 'hint' => 'Endpoint autenticado por token para agentes externos.'],
            'require_scopes' => ['label' => 'Exigir scopes do token', 'hint' => 'Cada chamada deve estar coberta pelos scopes do seu token.'],
            'enable_write_tools' => ['label' => 'Permitir ferramentas de escrita', 'hint' => 'Agentes podem criar e alterar documentos e elementos (evo.write.*). O token também precisa do scope mcp:write.'],
            'self_service' => ['label' => 'Página de autoatendimento de tokens', 'hint' => 'Usuários criam seus próprios tokens no manager.'],
            'rate_limit' => ['label' => 'Limite de requisições', 'hint' => 'Limita as requisições por minuto de cada usuário.'],
            'audit' => ['label' => 'Log de auditoria', 'hint' => 'Registrar cada chamada MCP no log de auditoria.'],
            'stream' => ['label' => 'Respostas em streaming', 'hint' => 'Permitir respostas em streaming (SSE).'],
        ],
    ],
];
