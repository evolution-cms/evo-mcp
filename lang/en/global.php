<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Access eMCP',
    'permission_manage' => 'Manage MCP servers',
    'permission_dispatch' => 'Dispatch async MCP tasks',
    'menu_tokens' => 'MCP tokens',
    'errors' => [
        'forbidden' => 'Forbidden',
        'scope_denied' => 'Scope denied',
        'server_not_found' => 'Server not found',
        'invalid_payload' => 'Invalid payload',
    ],
    'menu_settings' => 'MCP settings',
    'settings' => [
        'title' => 'MCP settings',
        'intro' => 'These switches apply to the whole site and are saved to :path. Other options (scopes, limits, allowlists) are edited in that file directly.',
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
        'error_write' => 'Could not save :path. Check that the web server can write to it.',
        'fields' => [
            'enable' => ['label' => 'Enable eMCP', 'hint' => 'Master switch for all MCP endpoints.'],
            'mode_internal' => ['label' => 'Manager endpoint', 'hint' => 'MCP over the manager session.'],
            'mode_api' => ['label' => 'API endpoint', 'hint' => 'Token-authenticated endpoint for external agents.'],
            'require_scopes' => ['label' => 'Require token scopes', 'hint' => 'Every call must be covered by the scopes of its token.'],
            'enable_write_tools' => ['label' => 'Allow write tools', 'hint' => 'Agents may create and change documents and elements (evo.write.*). A token also needs the mcp:write scope.'],
            'enable_destructive_tools' => ['label' => 'Allow destructive tools', 'hint' => 'Agents may call extension tools that delete data. Needs write tools as well; a token also needs the mcp:destructive scope.'],
            'enable_code_tools' => ['label' => 'Allow writing backend code', 'hint' => 'Agents may save snippets, plugins and TV bindings that run code on the server (@EVAL, @SELECT, @INCLUDE). Needs write tools as well; a token also needs the mcp:code scope, and its owner a permission to edit snippets, plugins or modules.'],
            'self_service' => ['label' => 'Token self-service page', 'hint' => 'Users create their own tokens in the manager.'],
            'rate_limit' => ['label' => 'Rate limiting', 'hint' => 'Limit the number of requests per minute for each user.'],
            'audit' => ['label' => 'Audit log', 'hint' => 'Record every MCP call in the audit log.'],
            'stream' => ['label' => 'Streaming responses', 'hint' => 'Allow streamed (SSE) responses.'],
        ],
    ],
];
