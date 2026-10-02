<!DOCTYPE html>
<html lang="en" class="{{ $darkTheme ? 'dark' : '' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MCP access tokens</title>
<style>
    :root { --bg:#f5f6f8; --card:#fff; --text:#1f2937; --muted:#6b7280; --line:#e5e7eb; --accent:#2563eb; --accent-text:#fff; --ok-bg:#ecfdf5; --ok-text:#065f46; --err-bg:#fef2f2; --err-text:#991b1b; --code-bg:#f3f4f6; --danger:#b91c1c; }
    html.dark { --bg:#1b1f27; --card:#232833; --text:#e5e7eb; --muted:#9ca3af; --line:#374151; --accent:#3b82f6; --ok-bg:#064e3b; --ok-text:#d1fae5; --err-bg:#7f1d1d; --err-text:#fee2e2; --code-bg:#111827; --danger:#f87171; }
    * { box-sizing:border-box; }
    body { margin:0; padding:20px; background:var(--bg); color:var(--text); font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
    h1 { font-size:20px; margin:0 0 4px; }
    h2 { font-size:15px; margin:0 0 12px; }
    p { margin:0 0 10px; }
    .muted { color:var(--muted); }
    .grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); gap:18px; margin-top:18px; }
    @media (max-width:900px) { .grid { grid-template-columns:1fr; } }
    .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px; }
    .notice { border-radius:8px; padding:10px 12px; margin-top:14px; }
    .notice.ok { background:var(--ok-bg); color:var(--ok-text); }
    .notice.err { background:var(--err-bg); color:var(--err-text); }
    code, .token { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:13px; }
    .token { display:block; background:var(--code-bg); border:1px solid var(--line); border-radius:6px; padding:10px; word-break:break-all; margin-top:8px; user-select:all; }
    label { display:block; font-weight:600; margin:12px 0 4px; }
    input[type=text], select { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:6px; background:var(--card); color:var(--text); }
    .scopes label { font-weight:400; margin:4px 0; display:flex; gap:8px; align-items:baseline; }
    .btn { display:inline-block; border:0; border-radius:6px; padding:8px 14px; font-weight:600; cursor:pointer; background:var(--accent); color:var(--accent-text); margin-top:14px; }
    .btn.link { background:transparent; color:var(--danger); padding:0; margin:0; font-weight:500; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
    th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
    .pill { display:inline-block; border:1px solid var(--line); border-radius:999px; padding:1px 8px; font-size:12px; margin:0 4px 4px 0; }
    .revoked { opacity:.55; }
    pre { background:var(--code-bg); border:1px solid var(--line); border-radius:6px; padding:10px; overflow:auto; font-size:12px; }
</style>
</head>
<body>
@if ($settingsUrl !== '')
    <a style="float:right" href="{{ $settingsUrl }}">{{ __('eMCP::global.menu_settings') }}</a>
@endif
<h1>MCP access tokens</h1>
<p class="muted">Tokens let an AI agent (Claude Code, Codex, ...) act on this site <strong>as you</strong>, limited to your role, your document groups and the scopes you pick here. Revoke a token the moment you stop using it.</p>

@if ($error !== '')
    <div class="notice err">{{ $error }}</div>
@endif
@if ($message !== '')
    <div class="notice ok">
        {{ $message }}
        @if ($plaintext !== '')
            <span class="token">{{ $plaintext }}</span>
        @endif
    </div>
@endif

@if ($authMode !== 'pat')
    <div class="notice err">The API is currently configured for <code>auth.mode = {{ $authMode }}</code>. Set <code>auth.mode</code> to <code>pat</code> in <code>core/custom/config/cms/settings/eMCP.php</code> for these tokens to be accepted.</div>
@endif

<div class="grid">
    <section class="card">
        <h2>Create a token</h2>
        <form method="post" action="{{ $createUrl }}">
            {!! csrf_field() !!}
            <label for="emcp-name">Name</label>
            <input type="text" id="emcp-name" name="name" maxlength="100" placeholder="e.g. Claude Code on my laptop" required>

            <label>Scopes</label>
            <div class="scopes">
                @foreach ($scopes as $scope)
                    <label>
                        <input type="checkbox" name="scopes[]" value="{{ $scope }}" {{ in_array($scope, ['mcp:read', 'mcp:call'], true) ? 'checked' : '' }}>
                        <span>
                            <code>{{ $scope }}</code>
                            @if ($scope === 'mcp:read') — list tools, read resources
                            @elseif ($scope === 'mcp:call') — call read-only tools
                            @elseif ($scope === 'mcp:write') — call <code>evo.write.*</code> tools (still limited by your permissions)
                            @elseif ($scope === 'mcp:admin') — administrative methods
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>

            <label for="emcp-expires">Expires</label>
            <select id="emcp-expires" name="expires">
                @foreach ($expiryChoices as $choice)
                    <option value="{{ $choice }}" {{ $choice === '90' ? 'selected' : '' }}>{{ $choice === 'never' ? 'Never' : 'In ' . $choice . ' days' }}</option>
                @endforeach
            </select>

            <button class="btn" type="submit">Create token</button>
        </form>
    </section>

    <section class="card">
        <h2>Your tokens</h2>
        @if ($tokens->isEmpty())
            <p class="muted">No tokens yet.</p>
        @else
            <table>
                <thead>
                <tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>Last used</th><th>Expires</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($tokens as $token)
                    <tr class="{{ $token->isUsable() ? '' : 'revoked' }}">
                        <td>{{ $token->name }}</td>
                        <td><code>{{ $token->token_prefix }}…</code></td>
                        <td>@foreach ($token->scopeList() as $scope)<span class="pill">{{ $scope }}</span>@endforeach</td>
                        <td>{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'never' }}</td>
                        <td>
                            @if ($token->isRevoked()) revoked
                            @elseif ($token->expires_at === null) never
                            @elseif ($token->isExpired()) expired
                            @else {{ $token->expires_at->toDateString() }}
                            @endif
                        </td>
                        <td>
                            @if ($token->isUsable())
                                <form method="post" action="{{ $revokeBaseUrl }}/{{ $token->id }}/revoke" onsubmit="return confirm('Revoke this token?');">
                                    {!! csrf_field() !!}
                                    <button class="btn link" type="submit">Revoke</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <h2 style="margin-top:22px">Connect an agent</h2>
        <p class="muted">Endpoint: <code>{{ $endpointUrl }}</code></p>
        <p class="muted">Claude Code:</p>
<pre>claude mcp add --transport http evo {{ $endpointUrl }} \
  --header "Authorization: Bearer &lt;token&gt;"</pre>
        <p class="muted">Codex (<code>~/.codex/config.toml</code>):</p>
<pre>[mcp_servers.evo]
url = "{{ $endpointUrl }}"
bearer_token_env_var = "EVO_MCP_TOKEN"</pre>
    </section>
</div>
</body>
</html>
