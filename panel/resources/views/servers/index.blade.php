@extends('layouts.app')

@section('title', 'Серверы')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-10">
    <div>
        <h1 class="page-title">Серверы</h1>
        <p class="page-subtitle">SSH-подключения, установка restic/rclone, бэкапы</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <form method="post" action="{{ route('servers.setup-all') }}">@csrf<button type="submit" class="btn btn-secondary !text-sm">Установить restic на всех</button></form>
        <form method="post" action="{{ route('servers.backup-all') }}">@csrf<button type="submit" class="btn btn-primary !text-sm">Бэкап всех готовых</button></form>
        <a href="{{ route('servers.create') }}" class="btn btn-primary !text-sm">+ Сервер</a>
    </div>
</div>

<div class="space-y-3">
    @forelse ($servers as $server)
        <div class="card p-5">
            <div class="flex flex-wrap gap-4 justify-between items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <a href="{{ $server->isWizardComplete() ? route('servers.show', $server) : route($server->wizardRoute(), $server) }}"
                           class="text-lg font-semibold text-slate-900 hover:text-brand-600 no-underline">{{ $server->name }}</a>
                        @if(!$server->isWizardComplete())
                            <span class="badge badge-info">мастер {{ min(4, $server->setup_step) }}/4</span>
                        @elseif($server->is_setup_complete)
                            <span class="badge badge-success">restic OK</span>
                        @elseif($server->readyForRemoteSetup())
                            <span class="badge badge-warning">нужна установка</span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</p>
                    <p class="text-xs text-slate-400 mt-1">
                        конфигов: {{ $server->modx_configs_count }}
                        · БД: {{ $server->project_databases_count }}
                        · проектов: {{ $server->projects_count }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 shrink-0">
                    @if($server->readyForRemoteSetup())
                        <form method="post" action="{{ route('servers.setup', $server) }}">
                            @csrf
                            <button type="submit" class="btn btn-blue !text-sm" title="SSH: install.sh — restic + rclone">Установить restic</button>
                        </form>
                    @endif
                    @if($server->readyForBackup())
                        <form method="post" action="{{ route('servers.backup', $server) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary !text-sm">Бэкап</button>
                        </form>
                    @elseif($server->isWizardComplete() && $server->is_setup_complete)
                        <span class="text-xs text-slate-400 self-center">нет проектов для бэкапа</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-slate-500">Нет серверов</div>
    @endforelse
</div>
@endsection
