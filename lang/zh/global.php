<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => '访问 eMCP',
    'permission_manage' => '管理 MCP 服务器',
    'permission_dispatch' => '调度异步 MCP 任务',
    'menu_tokens' => 'MCP 令牌',
    'errors' => [
        'forbidden' => '禁止访问',
        'scope_denied' => '权限范围不足',
        'server_not_found' => '未找到服务器',
        'invalid_payload' => '无效的负载数据',
    ],
    'menu_settings' => 'MCP 设置',
    'settings' => [
        'title' => 'MCP 设置',
        'intro' => '这些开关作用于整个站点，并保存在 :path 中。其他选项（scopes、限制、允许列表）请直接在该文件中编辑。',
        'save' => '保存设置',
        'saved' => '设置已保存。',
        'error_write' => '无法保存 :path。请检查 Web 服务器是否有权限写入该文件。',
        'fields' => [
            'enable' => ['label' => '启用 eMCP', 'hint' => '所有 MCP 端点的总开关。'],
            'mode_internal' => ['label' => '管理后台端点', 'hint' => '通过管理后台会话使用 MCP。'],
            'mode_api' => ['label' => 'API 端点', 'hint' => '供外部代理使用的令牌认证端点。'],
            'require_scopes' => ['label' => '要求令牌 scopes', 'hint' => '每次调用都必须在其令牌的 scopes 范围内。'],
            'enable_write_tools' => ['label' => '允许写入工具', 'hint' => '代理可以创建和修改文档与元素（evo.write.*）。令牌还需要 mcp:write scope。'],
            'self_service' => ['label' => '令牌自助页面', 'hint' => '用户在管理后台创建自己的令牌。'],
            'rate_limit' => ['label' => '请求频率限制', 'hint' => '限制每个用户每分钟的请求数。'],
            'audit' => ['label' => '审计日志', 'hint' => '将每次 MCP 调用记录到审计日志。'],
            'stream' => ['label' => '流式响应', 'hint' => '允许流式（SSE）响应。'],
        ],
    ],
];
