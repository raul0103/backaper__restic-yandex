@extends('layouts.app')

@section('title', $server->name)

@section('content')
@php
    $sshPort = (int) $server->ssh_port ?: 22;
    $sshCmd = $sshPort === 22
        ? 'ssh '.$server->ssh_user.'@'.$server->host
        : 'ssh '.$server->ssh_user.'@'.$server->host.' -p '.$sshPort;
@endphp

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="page-title">{{ $server->name }}</h1>
        <p class="page-subtitle">
            <span class="badge badge-info">{{ $server->kindLabel() }}</span>
            <span class="ml-2 font-mono text-xs">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</span>
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($server->readyForBackup())
            <form method="post" action="{{ route('servers.backup', $server) }}">@csrf
                <button type="submit" class="btn btn-primary">Запустить бэкап</button>
            </form>
        @endif
        @if ($server->readyForRemoteSetup())
            <form method="post" action="{{ route('servers.setup', $server) }}">@csrf
                <button type="submit" class="btn btn-secondary">
                    {{ $server->is_setup_complete ? 'Переустановить restic' : 'Установить restic' }}
                </button>
            </form>
        @endif
        <a href="{{ route('servers.restore', $server) }}" class="btn btn-secondary">Восстановление</a>
        <a href="{{ route('servers.edit', $server) }}" class="btn btn-secondary">Изменить</a>
        <a href="{{ route('servers.wizard.content', $server) }}" class="btn btn-ghost">Базы данных</a>
    </div>
</div>

@if (! $server->is_setup_complete)
    <div class="alert alert-warning mb-6">
        restic не готов (удалили папки на Диске, сменили пароль/токен/папку, или ещё не ставили).
        Нажмите <strong>«Установить restic»</strong> / <strong>«Переустановить restic»</strong> выше.
    </div>
@endif

<div class="flex flex-wrap gap-2 mb-6">
    <button type="button" data-server-tab="overview" class="btn btn-primary">Обзор</button>
    <button type="button" data-server-tab="manual" class="btn btn-secondary">Ручной запуск</button>
</div>

{{-- ===== Обзор ===== --}}
<div data-server-panel="overview">
    <div class="grid sm:grid-cols-3 gap-4 mb-8">
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">restic</div>
            <div class="font-semibold mt-1">{{ $server->is_setup_complete ? 'Установлен' : 'Не установлен' }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Путей</div>
            <div class="font-semibold mt-1">{{ $server->backupPaths->where('is_enabled', true)->count() }} / {{ $server->backupPaths->count() }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-slate-500 font-medium">Баз</div>
            <div class="font-semibold mt-1">{{ $server->databases->where('is_enabled', true)->count() }} / {{ $server->databases->count() }}</div>
        </div>
    </div>

    <section class="mb-8">
        <h2 class="section-title">Файлы</h2>
        <div class="card p-4">
            @php($path = $server->backupPaths->first())
            <div class="font-medium">{{ $path?->displayName() ?? $server->fullBackupTarget()['label'] }}</div>
            <div class="text-xs font-mono text-slate-400 mt-1">{{ $path?->path ?? $server->fullBackupTarget()['path'] }}</div>
            <p class="text-xs text-slate-500 mt-2">Бэкапится весь аккаунт одним снапшотом (restic режет данные на pack’и, upload с лимитом). Пути настраивать не нужно.</p>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title">Базы данных</h2>
        <div class="card divide-y divide-slate-100">
            @forelse ($server->databases as $db)
                <div class="p-4 flex justify-between gap-3">
                    <div>
                        <div class="font-medium font-mono">{{ $db->database_name }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">
                            {{ $db->database_server }} · {{ $db->database_user }}
                            @if ($db->source) · {{ $db->source }} @endif
                        </div>
                    </div>
                    @if ($db->is_enabled)
                        <span class="badge badge-success">вкл</span>
                    @else
                        <span class="badge badge-warning">выкл</span>
                    @endif
                </div>
            @empty
                <p class="p-4 text-sm text-slate-400">Нет баз — откройте «Базы данных» и нажмите «Найти базы»</p>
            @endforelse
        </div>
    </section>

    <section>
        <h2 class="section-title">Последние бэкапы</h2>
        @forelse ($server->backupRuns as $run)
            <a href="{{ route('backup-runs.show', $run) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
                <div class="flex justify-between gap-3">
                    <span class="font-medium">#{{ $run->id }} · {{ $run->started_at?->format('d.m.Y H:i') }}</span>
                    <span class="badge {{ $run->status === 'completed' ? 'badge-success' : ($run->status === 'running' ? 'badge-info' : 'badge-warning') }}">{{ $run->status }}</span>
                </div>
            </a>
        @empty
            <p class="text-sm text-slate-400">Пока не запускали</p>
        @endforelse
    </section>
</div>

{{-- ===== Ручной запуск ===== --}}
<div data-server-panel="manual" class="hidden space-y-6">
    <div class="help-box">
        Через панель бэкап иногда обрывается (Beget убивает фоновый процесс).
        Надёжнее запускать вручную по SSH в <code>screen</code>.
    </div>

    <section class="card p-6">
        <h2 class="section-title">1. Подключение</h2>
        <pre class="code-block">{{ $sshCmd }}</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">2. Окружение</h2>
        <pre class="code-block">source ~/backaper/backaper.env</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">3. Бэкап всего хостинга (файлы)</h2>
        <p class="text-sm text-slate-500 mb-3">Один снапшот всего домашнего каталога. Upload ограничен (~2 MiB/s).</p>
        <pre class="code-block mb-3">screen -S backup
source ~/backaper/backaper.env
bash ~/backaper/scripts/test-full-home-backup.sh</pre>
        <p class="text-xs text-slate-500 mb-3">Отключиться от screen не останавливая бэкап: <code>Ctrl+A</code>, затем <code>D</code>. Вернуться: <code>screen -r backup</code></p>
        <p class="text-sm text-slate-600 mb-2">Если снова обрывает — медленнее:</p>
        <pre class="code-block">BACKAPER_UPLOAD_LIMIT_KIB=1024 bash ~/backaper/scripts/test-full-home-backup.sh</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">4. Проверка</h2>
        <pre class="code-block">source ~/backaper/backaper.env
ps aux | grep restic | grep -v grep
restic snapshots --tag path:home
restic stats latest</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">5. Если скрипта нет на сервере</h2>
        <p class="text-sm text-slate-500 mb-3">Нажмите «Переустановить restic» выше — скрипт зальётся вместе с установкой. Или после любого бэкапа из панели.</p>
        <pre class="code-block">ls -la ~/backaper/scripts/test-full-home-backup.sh</pre>
    </section>
</div>

<script>
(function () {
    const tabs = document.querySelectorAll('[data-server-tab]');
    const panels = document.querySelectorAll('[data-server-panel]');
    function show(name) {
        tabs.forEach(function (btn) {
            const active = btn.getAttribute('data-server-tab') === name;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-secondary', !active);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-server-panel') !== name);
        });
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    }
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () { show(btn.getAttribute('data-server-tab')); });
    });
    const hash = (location.hash || '').replace('#', '');
    show(hash === 'manual' ? 'manual' : 'overview');
})();
</script>
@endsection
