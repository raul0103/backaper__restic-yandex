@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-10">
    <div>
        <h1 class="page-title">Панель управления</h1>
        <p class="page-subtitle">MODX-проекты, restic и rclone по SSH</p>
    </div>
    <a href="{{ route('servers.create') }}" class="btn btn-primary shrink-0">Добавить сервер</a>
</div>

<div class="grid md:grid-cols-2 gap-8">
    <section>
        <h2 class="section-title">Серверы</h2>
        @forelse ($servers as $server)
            <a href="{{ $server->isWizardComplete() ? route('servers.show', $server) : route($server->wizardRoute(), $server) }}"
               class="card card-hover block mb-3 p-4 no-underline text-inherit">
                <div class="flex justify-between items-start gap-2">
                    <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                    @if(!$server->isWizardComplete())
                        <span class="badge badge-info">шаг {{ min(4, $server->setup_step) }}/4</span>
                    @elseif($server->is_setup_complete)
                        <span class="badge badge-success">настроен</span>
                    @else
                        <span class="badge badge-warning">не настроен</span>
                    @endif
                </div>
                <div class="text-sm text-slate-500 mt-1.5">{{ $server->ssh_user }}@{{ $server->host }} · {{ $server->projects_count }} проект(ов)</div>
            </a>
        @empty
            <div class="card p-8 text-center text-slate-500">
                <p class="mb-4">Серверов пока нет</p>
                <a href="{{ route('servers.create') }}" class="btn btn-primary">Добавить первый сервер</a>
            </div>
        @endforelse
    </section>

    <section>
        <h2 class="section-title">Последние бэкапы</h2>
        @forelse ($recentRuns as $run)
            <a href="{{ route('backup-runs.show', $run) }}" class="card card-hover block mb-3 p-4 no-underline text-inherit">
                <div class="flex justify-between items-start gap-2 text-sm">
                    <span class="font-medium text-slate-800">
                        {{ $run->server->name }}@if($run->project) <span class="text-slate-400">/</span> {{ $run->project->name }}@endif
                    </span>
                    @if($run->status === 'completed')
                        <span class="badge badge-success">{{ $run->status }}</span>
                    @elseif($run->status === 'running')
                        <span class="badge bg-blue-100 text-blue-800">{{ $run->status }}</span>
                    @else
                        <span class="badge badge-error">{{ $run->status }}</span>
                    @endif
                </div>
                <div class="text-xs text-slate-400 mt-1.5">{{ $run->created_at?->format('d.m.Y H:i') }}</div>
            </a>
        @empty
            <div class="card p-6 text-slate-500 text-sm">Бэкапов пока не было</div>
        @endforelse
    </section>
</div>
@endsection
