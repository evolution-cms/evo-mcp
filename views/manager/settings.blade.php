<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $darkTheme ? 'dark' : '' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('eMCP::global.settings.title') }}</title>
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
    .setting { display:flex; gap:10px; align-items:flex-start; padding:10px 0; border-bottom:1px solid var(--line); margin:0; font-weight:400; }
    .setting:last-of-type { border-bottom:0; }
    .setting input { margin-top:3px; }
    .setting strong { display:block; }
    .top-link { float:right; }
    pre { background:var(--code-bg); border:1px solid var(--line); border-radius:6px; padding:10px; overflow:auto; font-size:12px; }
</style>
</head>
<body>
<a class="top-link" href="{{ $tokensUrl }}">{{ __('eMCP::global.menu_tokens') }}</a>
<h1>{{ __('eMCP::global.settings.title') }}</h1>
<p class="muted">{!! str_replace(':path', '<code>' . e($filePath) . '</code>', e(__('eMCP::global.settings.intro'))) !!}</p>

@if ($error !== '')
    <div class="notice err">{{ $error }}</div>
@endif
@if ($message !== '')
    <div class="notice ok">{{ $message }}</div>
@endif

<section class="card" style="margin-top:18px; max-width:760px">
    <form method="post" action="{{ $saveUrl }}">
        {!! csrf_field() !!}
        @foreach ($fields as $field)
            <label class="setting">
                <input type="checkbox" name="settings[{{ $field['key'] }}]" value="1" {{ $field['checked'] ? 'checked' : '' }}>
                <span>
                    <strong>{{ $field['label'] }}</strong>
                    <span class="muted">{{ $field['hint'] }}</span>
                </span>
            </label>
        @endforeach
        <button class="btn" type="submit">{{ __('eMCP::global.settings.save') }}</button>
    </form>
</section>
</body>
</html>
