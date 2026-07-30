<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Backaper') — Backaper v2</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['DM Sans', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdfa', 100:'#ccfbf1', 500:'#14b8a6', 600:'#0d9488', 700:'#0f766e' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'DM Sans', system-ui, sans-serif; }
        .page-title { font-size: 1.875rem; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; }
        .page-subtitle { color: #64748b; margin-top: 0.25rem; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .card-hover { transition: border-color .15s, box-shadow .15s; }
        .card-hover:hover { border-color:#99f6e4; box-shadow:0 4px 16px rgba(13,148,136,.08); }
        .label { display:block; font-size:.875rem; font-weight:500; color:#475569; margin-bottom:.375rem; }
        .input, .textarea { width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:.625rem .875rem; background:#fff; color:#0f172a; font-size:.9375rem; }
        .input:focus, .textarea:focus { outline:none; border-color:#14b8a6; box-shadow:0 0 0 3px rgba(20,184,166,.15); }
        .textarea { resize:vertical; min-height:80px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.375rem; border-radius:10px; padding:.5rem 1rem; font-size:.875rem; font-weight:600; transition:all .15s; cursor:pointer; border:none; text-decoration:none; }
        .btn-primary { background:#0d9488; color:#fff; } .btn-primary:hover { background:#0f766e; }
        .btn-secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; } .btn-secondary:hover { background:#f8fafc; }
        .btn-ghost { background:transparent; color:#0d9488; padding:.25rem .5rem; font-weight:500; }
        .btn-danger { background:transparent; color:#dc2626; font-weight:500; font-size:.875rem; }
        .alert { border-radius:12px; padding:.875rem 1rem; margin-bottom:1.5rem; font-size:.9375rem; }
        .alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .alert-warning { background:#fffbeb; border:1px solid #fcd34d; color:#92400e; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .badge { display:inline-flex; font-size:.75rem; font-weight:600; padding:.2rem .625rem; border-radius:9999px; }
        .badge-success { background:#d1fae5; color:#047857; }
        .badge-warning { background:#fef3c7; color:#b45309; }
        .badge-info { background:#dbeafe; color:#1d4ed8; }
        .section-title { font-size:1.125rem; font-weight:600; color:#0f172a; margin-bottom:1rem; }
        .code-block { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.875rem 1rem; font-family:ui-monospace,monospace; font-size:.8125rem; color:#334155; overflow-x:auto; white-space:pre-wrap; word-break:break-all; }
        .nav-link { color:#64748b; font-size:.875rem; font-weight:500; padding:.375rem .75rem; border-radius:8px; }
        .nav-link:hover { color:#0d9488; background:#f0fdfa; }
        .nav-link-active { color:#0d9488; background:#ccfbf1; }
        .step-pill { display:inline-flex; align-items:center; gap:.5rem; padding:.375rem .875rem; border-radius:9999px; font-size:.8125rem; font-weight:600; border:1px solid #e2e8f0; color:#94a3b8; background:#fff; text-decoration:none; }
        .step-pill-active { border-color:#14b8a6; background:#f0fdfa; color:#0f766e; }
        .step-pill-done { border-color:#cbd5e1; color:#64748b; background:#f8fafc; }
        .log-block { background:#1e293b; color:#e2e8f0; border-radius:12px; padding:1rem 1.25rem; font-family:ui-monospace,monospace; font-size:.8125rem; line-height:1.6; overflow-x:auto; white-space:pre-wrap; }
        .help-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; font-size:.875rem; color:#475569; }
        .help-box strong { color:#0f172a; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen antialiased">
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-slate-900 font-bold text-lg tracking-tight no-underline">
                <span class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center text-sm font-bold">B2</span>
                Backaper <span class="text-slate-400 font-medium text-sm">v2</span>
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Панель</a>
                <a href="{{ route('servers.index') }}" class="nav-link {{ request()->routeIs('servers.*') && !request()->routeIs('servers.create') ? 'nav-link-active' : '' }}">Серверы</a>
                <a href="{{ route('backup-batches.create') }}" class="nav-link {{ request()->routeIs('backup-batches.*') ? 'nav-link-active' : '' }}">Бэкап</a>
                <a href="{{ route('backup-runs.index') }}" class="nav-link {{ request()->routeIs('backup-runs.*') ? 'nav-link-active' : '' }}">История</a>
                <a href="{{ route('guide') }}" class="nav-link {{ request()->routeIs('guide') ? 'nav-link-active' : '' }}">Как пользоваться</a>
                <a href="{{ route('servers.create') }}" class="btn btn-primary ml-2 !py-2 !px-3.5">+ Сервер</a>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="max-w-5xl mx-auto px-4 sm:px-6 pb-8 text-center text-xs text-slate-400">
        Backaper v2 · полный бэкап сервера + отдельные дампы БД · Яндекс.Диск
    </footer>
</body>
</html>
