<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'eMCPへのアクセス',
    'permission_manage' => 'MCPサーバーの管理',
    'permission_dispatch' => '非同期MCPタスクの実行',
    'menu_tokens' => 'MCPトークン',
    'errors' => [
        'forbidden' => '禁止されています',
        'scope_denied' => 'スコープが拒否されました',
        'server_not_found' => 'サーバーが見つかりません',
        'invalid_payload' => '無効なペイロードです',
    ],
    'menu_settings' => 'MCP 設定',
    'settings' => [
        'title' => 'MCP 設定',
        'intro' => 'これらのスイッチはサイト全体に適用され、:path に保存されます。その他のオプション（スコープ、制限、許可リスト）はこのファイルで直接編集します。',
        'save' => '設定を保存',
        'saved' => '設定を保存しました。',
        'error_write' => ':path を保存できませんでした。Web サーバーがこのファイルに書き込めるか確認してください。',
        'fields' => [
            'enable' => ['label' => 'eMCP を有効化', 'hint' => 'すべての MCP エンドポイントのマスタースイッチです。'],
            'mode_internal' => ['label' => 'マネージャーエンドポイント', 'hint' => 'マネージャーのセッション経由の MCP です。'],
            'mode_api' => ['label' => 'API エンドポイント', 'hint' => '外部エージェント向けのトークン認証エンドポイントです。'],
            'require_scopes' => ['label' => 'トークンのスコープを必須にする', 'hint' => '各呼び出しはトークンのスコープの範囲内である必要があります。'],
            'enable_write_tools' => ['label' => '書き込みツールを許可', 'hint' => 'エージェントがドキュメントとエレメントを作成・変更できます（evo.write.*）。トークンには mcp:write スコープも必要です。'],
            'self_service' => ['label' => 'トークンのセルフサービスページ', 'hint' => 'ユーザーがマネージャーで自分のトークンを作成します。'],
            'rate_limit' => ['label' => 'レート制限', 'hint' => 'ユーザーごとの1分あたりのリクエスト数を制限します。'],
            'audit' => ['label' => '監査ログ', 'hint' => 'すべての MCP 呼び出しを監査ログに記録します。'],
            'stream' => ['label' => 'ストリーミング応答', 'hint' => 'ストリーミング（SSE）応答を許可します。'],
        ],
    ],
];
