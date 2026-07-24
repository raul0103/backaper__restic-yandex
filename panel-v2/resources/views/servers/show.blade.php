@extends('layouts.app')

@section('title', $server->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-8">
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
        <a href="{{ route('servers.restore', $server) }}" class="btn btn-secondary">Восстановление</a>
        <a href="{{ route('servers.edit', $server) }}" class="btn btn-secondary">Изменить</a>
        <a href="{{ route('servers.wizard.content', $server) }}" class="btn btn-ghost">Базы данных</a>
    </div>
</div>

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
        <p class="text-xs text-slate-500 mt-2">Бэкапится целиком. Пути настраивать не нужно.</p>
    </div>
</section>

<section class="mb-8">
    <h2 class="section-title">Базы данных</h2>
    <div class="card divide-y divide-slate-100">
        @forelse ($server->databases as $db)
            <div class="p-4 flex justify-between gap-3">
                <div>
                    <div class="font-medium">{{ $db->displayName() }}</div>
                    <div class="text-xs font-mono text-slate-400">{{ $db->database_name }} · {{ $db->source }}</div>
                </div>
                @if ($db->is_enabled)
                    <span class="badge badge-success">вкл</span>
                @else
                    <span class="badge badge-warning">выкл</span>
                @endif
            </div>
        @empty
            <p class="p-4 text-sm text-slate-400">Нет баз — можно добавить через «Пути и базы»</p>
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
@endsection
