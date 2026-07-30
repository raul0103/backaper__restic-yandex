@extends('layouts.app')

@section('title', 'Серверы')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="page-title">Серверы</h1>
        <p class="page-subtitle">SSH + restic · дата последнего бэкапа (цель ~ раз в месяц)</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($passtoreConfigured)
            <form method="post" action="{{ route('servers.sync-passtore') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Из Passtore</button>
            </form>
        @else
            <span class="text-xs text-amber-700 self-center" title="Добавьте PASSTORE_TOKEN в .env">Passtore не настроен</span>
        @endif
        <a href="{{ route('backup-batches.create') }}" class="btn btn-secondary">Массовый бэкап</a>
        <a href="{{ route('servers.create') }}" class="btn btn-primary">+ Сервер</a>
    </div>
</div>

@forelse ($servers as $server)
    <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-5 mb-3 no-underline text-inherit">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-lg font-semibold text-slate-900">{{ $server->name }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    <span class="badge badge-info">{{ $server->kindLabel() }}</span>
                    <span class="ml-2 font-mono">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</span>
                </div>
                <div class="text-sm mt-2" style="{{ $server->backupAgeStyle() }}">
                    {{ $server->backupAgeLabel() }}
                </div>
            </div>
            <div class="text-sm shrink-0 flex flex-col items-end gap-1">
                @if ($server->is_setup_complete)
                    <span class="badge badge-success">restic ок</span>
                @else
                    <span class="badge badge-warning">restic не установлен</span>
                @endif
                @if ($server->passtore_synced_at)
                    <span class="text-[11px] text-slate-400">Passtore {{ $server->passtore_synced_at->format('d.m H:i') }}</span>
                @endif
            </div>
        </div>
    </a>
@empty
    <div class="card p-8 text-center">
        <p class="text-slate-500 mb-4">Серверов пока нет</p>
        <div class="flex flex-wrap justify-center gap-2">
            @if ($passtoreConfigured)
                <form method="post" action="{{ route('servers.sync-passtore') }}">@csrf
                    <button type="submit" class="btn btn-primary">Загрузить из Passtore</button>
                </form>
            @endif
            <a href="{{ route('servers.create') }}" class="btn btn-secondary">Добавить вручную</a>
        </div>
    </div>
@endforelse
@endsection
