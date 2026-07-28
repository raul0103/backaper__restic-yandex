@extends('layouts.app')

@section('title', $server->name)

@section('content')
@php
    $sshPort = (int) $server->ssh_port ?: 22;
    $sshCmd = $sshPort === 22
        ? 'ssh '.$server->ssh_user.'@'.$server->host
        : 'ssh '.$server->ssh_user.'@'.$server->host.' -p '.$sshPort;
    $backupRoot = $server->isVps() ? 'весь сервер (/)' : 'домашний каталог (~)';
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
        @if ($server->readyForRemoteSetup())
            <form method="post" action="{{ route('servers.setup', $server) }}">@csrf
                <button type="submit" class="btn btn-secondary">
                    {{ $server->is_setup_complete ? 'Переустановить restic' : 'Установить restic' }}
                </button>
            </form>
        @endif
        <a href="{{ route('servers.restore', $server) }}" class="btn btn-secondary">Восстановление</a>
        <a href="{{ route('servers.edit', $server) }}" class="btn btn-secondary">Изменить</a>
    </div>
</div>

@if (! $server->is_setup_complete)
    <div class="alert alert-warning mb-6">
        restic не готов. Нажмите <strong>«Установить restic»</strong> / <strong>«Переустановить restic»</strong> выше.
    </div>
@else
    <div class="help-box mb-6">
        Панель только настраивает SSH и restic. Бэкапы файлов и баз — вручную по CLI на сервере (ниже).
    </div>
@endif

<div class="grid sm:grid-cols-2 gap-4 mb-8">
    <div class="card p-4">
        <div class="text-xs text-slate-500 font-medium">restic</div>
        <div class="font-semibold mt-1">{{ $server->is_setup_complete ? 'Установлен' : 'Не установлен' }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500 font-medium">Объём файлов</div>
        <div class="font-semibold mt-1">{{ $backupRoot }}</div>
        <div class="text-xs font-mono text-slate-400 mt-1">{{ $server->fullBackupTarget()['path'] }}</div>
    </div>
</div>

<div class="space-y-6">
    <section class="card p-6">
        <h2 class="section-title">1. Подключение</h2>
        <pre class="code-block">{{ $sshCmd }}</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">2. Окружение</h2>
        <pre class="code-block">source ~/backaper/backaper.env</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">3. Бэкап файлов</h2>
        <p class="text-sm text-slate-500 mb-3">
            Restic-снапшот {{ $backupRoot }}. Upload ограничен (~2 MiB/s). Базы сюда не входят.
        </p>
        <pre class="code-block mb-3">screen -S backup-files
source ~/backaper/backaper.env
bash ~/backaper/scripts/backup-files.sh</pre>
        <p class="text-xs text-slate-500 mb-3">Отключиться от screen: <code>Ctrl+A</code>, затем <code>D</code>. Вернуться: <code>screen -r backup-files</code></p>
        <p class="text-sm text-slate-600 mb-2">Медленнее (если убивает процесс):</p>
        <pre class="code-block">BACKAPER_UPLOAD_LIMIT_KIB=1024 bash ~/backaper/scripts/backup-files.sh</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">4. Дамп баз</h2>
        <p class="text-sm text-slate-500 mb-3">
            Скрипт сам находит <code>config.inc.php</code> / <code>wp-config.php</code> / <code>.env</code>,
            парсит доступы и заливает <code>.sql.gz</code> на Диск в <code>…/databases/{db}/</code>.
        </p>
        <pre class="code-block mb-3">screen -S backup-db
source ~/backaper/backaper.env
bash ~/backaper/scripts/backup-databases.sh</pre>
        <p class="text-xs text-slate-500">Нужен <code>php-cli</code> на сервере для разбора конфигов.</p>
    </section>

    <section class="card p-6">
        <h2 class="section-title">5. Проверка</h2>
        <pre class="code-block">source ~/backaper/backaper.env
ps aux | grep -E 'restic|mysqldump|rclone' | grep -v grep
restic snapshots --tag path:home
rclone lsl "${BACKAPER_RCLONE_REMOTE}:${BACKAPER_CLOUD_PREFIX}/" | tail -20</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">6. Если скриптов нет</h2>
        <p class="text-sm text-slate-500 mb-3">Нажмите «Переустановить restic» выше — скрипты зальются вместе с установкой.</p>
        <pre class="code-block">ls -la ~/backaper/scripts/backup-files.sh ~/backaper/scripts/backup-databases.sh</pre>
    </section>
</div>
@endsection
