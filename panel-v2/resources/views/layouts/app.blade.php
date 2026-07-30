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
        .input, .textarea, .select { width:100%; border:1px solid #cbd5e1; border-radius:10px; padding:.625rem .875rem; background:#fff; color:#0f172a; font-size:.9375rem; }
        .input:focus, .textarea:focus, .select:focus { outline:none; border-color:#14b8a6; box-shadow:0 0 0 3px rgba(20,184,166,.15); }
        .textarea { resize:vertical; min-height:80px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.375rem; border-radius:10px; padding:.5rem 1rem; font-size:.875rem; font-weight:600; transition:all .15s; cursor:pointer; border:none; text-decoration:none; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn-primary { background:#0d9488; color:#fff; } .btn-primary:hover:not(:disabled) { background:#0f766e; }
        .btn-secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; } .btn-secondary:hover:not(:disabled) { background:#f8fafc; }
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
        .badge-vps { background:#ede9fe; color:#5b21b6; }
        .badge-hosting { background:#ccfbf1; color:#0f766e; }
        .server-kind-vps { border-left:4px solid #8b5cf6; }
        .server-kind-hosting { border-left:4px solid #14b8a6; }
        .section-title { font-size:1.125rem; font-weight:600; color:#0f172a; margin-bottom:1rem; }
        .code-block { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.875rem 1rem; font-family:ui-monospace,monospace; font-size:.8125rem; color:#334155; overflow-x:auto; white-space:pre-wrap; word-break:break-all; }
        .nav-link { color:#64748b; font-size:.8125rem; font-weight:500; padding:.4rem .7rem; border-radius:8px; text-decoration:none; white-space:nowrap; }
        .nav-link:hover { color:#0d9488; background:#f0fdfa; }
        .nav-link-active { color:#0d9488; background:#ccfbf1; }
        .step-pill { display:inline-flex; align-items:center; gap:.5rem; padding:.375rem .875rem; border-radius:9999px; font-size:.8125rem; font-weight:600; border:1px solid #e2e8f0; color:#94a3b8; background:#fff; text-decoration:none; }
        .step-pill-active { border-color:#14b8a6; background:#f0fdfa; color:#0f766e; }
        .step-pill-done { border-color:#cbd5e1; color:#64748b; background:#f8fafc; }
        .log-block { background:#1e293b; color:#e2e8f0; border-radius:12px; padding:1rem 1.25rem; font-family:ui-monospace,monospace; font-size:.8125rem; line-height:1.6; overflow-x:auto; white-space:pre-wrap; }
        .help-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; font-size:.875rem; color:#475569; }
        .help-box strong { color:#0f172a; }
        .nav-map { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; font-size:.75rem; color:#94a3b8; margin-bottom:1.5rem; }
        .nav-map a { color:#0f766e; text-decoration:none; font-weight:500; }
        .nav-map a:hover { text-decoration:underline; }
        .nav-map .sep { color:#cbd5e1; }
        .queue-bar { position:sticky; bottom:0; z-index:20; background:rgba(255,255,255,.96); backdrop-filter:blur(8px); border-top:1px solid #e2e8f0; box-shadow:0 -8px 24px rgba(15,23,42,.06); }
        .server-row.selected { border-color:#5eead4; background:#f0fdfa; }
        @media (max-width: 768px) {
            .nav-desktop { display:none; }
        }
        @media (min-width: 769px) {
            .nav-mobile-toggle { display:none; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen antialiased">
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-slate-900 font-bold text-lg tracking-tight no-underline shrink-0">
                <span class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center text-sm font-bold">B2</span>
                Backaper <span class="text-slate-400 font-medium text-sm">v2</span>
            </a>

            <div class="nav-desktop flex items-center gap-0.5 overflow-x-auto">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" title="Активные бэкапы">Панель</a>
                <a href="{{ route('servers.index') }}" class="nav-link {{ request()->routeIs('servers.index') || request()->routeIs('servers.show') || request()->routeIs('servers.edit') ? 'nav-link-active' : '' }}" title="Список SSH и быстрый запуск">Серверы</a>
                <a href="{{ route('backup-batches.create') }}" class="nav-link {{ request()->routeIs('backup-batches.*') ? 'nav-link-active' : '' }}" title="Собрать очередь бэкапов">Очередь</a>
                <a href="{{ route('backup-runs.index') }}" class="nav-link {{ request()->routeIs('backup-runs.*') ? 'nav-link-active' : '' }}" title="Логи всех запусков">История</a>
                <a href="{{ route('guide') }}" class="nav-link {{ request()->routeIs('guide') ? 'nav-link-active' : '' }}">Справка</a>
                <a href="{{ route('servers.create') }}" class="btn btn-primary ml-2 !py-1.5 !px-3 !text-xs">+ Сервер</a>
            </div>

            <button type="button" id="nav-mobile-btn" class="nav-mobile-toggle btn btn-secondary !py-1.5 !px-3" aria-label="Меню">☰</button>
        </div>
        <div id="nav-mobile-panel" class="hidden border-t border-slate-100 bg-white px-4 py-3 space-y-1 md:hidden">
            <a href="{{ route('dashboard') }}" class="nav-link block {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">Панель — активные бэкапы</a>
            <a href="{{ route('servers.index') }}" class="nav-link block {{ request()->routeIs('servers.index') ? 'nav-link-active' : '' }}">Серверы — выбор и запуск</a>
            <a href="{{ route('backup-batches.create') }}" class="nav-link block {{ request()->routeIs('backup-batches.create') ? 'nav-link-active' : '' }}">Очередь — массовый бэкап</a>
            <a href="{{ route('backup-runs.index') }}" class="nav-link block {{ request()->routeIs('backup-runs.*') ? 'nav-link-active' : '' }}">История — логи</a>
            <a href="{{ route('guide') }}" class="nav-link block {{ request()->routeIs('guide') ? 'nav-link-active' : '' }}">Справка</a>
            <a href="{{ route('servers.create') }}" class="btn btn-primary !mt-2 w-full">+ Сервер</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-10 @yield('main_class')">
        @hasSection('nav_map')
            <div class="nav-map">@yield('nav_map')</div>
        @else
            <div class="nav-map">
                <a href="{{ route('dashboard') }}">Панель</a>
                <span class="sep">→</span>
                <a href="{{ route('servers.index') }}">Серверы</a>
                <span class="sep">→</span>
                <a href="{{ route('backup-batches.create') }}">Очередь</a>
                <span class="sep">→</span>
                <a href="{{ route('backup-runs.index') }}">История / логи</a>
            </div>
        @endif

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
        Backaper v2 · серверы по очереди · Яндекс.Диск
    </footer>

    <script>
    (function () {
        const btn = document.getElementById('nav-mobile-btn');
        const panel = document.getElementById('nav-mobile-panel');
        if (!btn || !panel) return;
        btn.addEventListener('click', function () {
            panel.classList.toggle('hidden');
        });
    })();
    </script>
    @stack('scripts')
</body>
</html>
