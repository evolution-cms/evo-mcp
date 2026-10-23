<?php

return [
    'enable' => true,
    'toolset_version' => '1.0',

    'mode' => [
        'internal' => true,
        'api' => true,
    ],

    'route' => [
        'manager_prefix' => 'emcp',
        'api_prefix' => 'mcp',
    ],

    'auth' => [
        // pat      - personal access tokens issued in the manager / by artisan (no extra packages)
        // sapi_jwt - JWT issued by seiger/sapi
        // none     - no API authentication (development only)
        // In every mode the request runs as the authenticated manager user, with that user's permissions.
        'mode' => 'pat',
        'require_scopes' => true,
        // Extra scope a token needs to call evo.write.* tools.
        'write_scope' => 'mcp:write',
        // Extra scope (on top of write_scope) a token needs to call DestructiveSite tools.
        'destructive_scope' => 'mcp:destructive',
        // Extra scope (on top of write_scope) a token needs to store backend code: snippets,
        // plugins, @EVAL/@SELECT/@INCLUDE bindings, ExecutesCode tools.
        'code_scope' => 'mcp:code',
        'scope_map' => [
            'mcp:read' => [
                'initialize',
                'ping',
                'tools/list',
                'resources/list',
                'resources/read',
                'prompts/list',
                'prompts/get',
                'completion/complete',
                'resources/templates/list',
                // JSON-RPC notifications (notifications/initialized, notifications/cancelled, ...)
                'notifications/*',
            ],
            'mcp:call' => ['tools/call'],
            'mcp:admin' => ['admin/*'],
        ],
    ],

    'tokens' => [
        // Manager page where a user creates their own tokens: {manager_url}/{manager_prefix}/tokens
        'self_service' => true,
    ],

    'acl' => [
        'permission' => 'emcp',
    ],

    'queue' => [
        'driver' => 'stask',
        'failover' => 'sync',
    ],

    'rate_limit' => [
        'enabled' => true,
        'per_minute' => 60,
    ],

    'limits' => [
        'max_payload_kb' => 256,
        'max_result_items' => 100,
        'max_result_bytes' => 1048576,
    ],

    'stream' => [
        'enabled' => false,
        'max_stream_seconds' => 120,
        'heartbeat_seconds' => 15,
        'abort_on_disconnect' => true,
    ],

    'logging' => [
        'channel' => 'emcp',
        'audit_enabled' => true,
        'redact_keys' => [
            'authorization',
            'token',
            'jwt',
            'secret',
            'cookie',
            'password',
            'api_key',
        ],
    ],

    'security' => [
        'allow_servers' => ['*'],
        'deny_tools' => [],
        'enable_write_tools' => false,
        // Tools that delete data (DestructiveSite). Off: they are hidden and refused.
        'enable_destructive_tools' => false,
        // Writing code the server executes (PHP, code bindings). Off: refused, even for admins.
        'enable_code_tools' => false,
    ],

    'actor' => [
        'mode' => 'initiator',
        'service_username' => 'MCP',
        'service_role' => 'MCP',
        'block_login' => true,
    ],

    'trace' => [
        'header' => 'X-Trace-Id',
        'generate_if_missing' => true,
    ],

    'idempotency' => [
        'header' => 'Idempotency-Key',
        'ttl_seconds' => 86400,
        'storage' => 'stask_meta',
    ],

    'domain' => [
        'content' => [
            'max_depth' => 6,
            'max_limit' => 100,
            'max_offset' => 5000,
        ],
        'models' => [
            'max_offset' => 5000,
            'allow' => [
                'SiteTemplate',
                'SiteTmplvar',
                'SiteTmplvarContentvalue',
                'SiteSnippet',
                'SitePlugin',
                'SiteModule',
                'Category',
                'User',
                'UserAttribute',
                'UserRole',
                'Permissions',
                'PermissionsGroups',
                'RolePermissions',
            ],
        ],
    ],
];
