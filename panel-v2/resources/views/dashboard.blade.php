@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">SSH-серверы и очередь массовых бэкапов (по одному, без перегрузки Яндекса)</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('backup-runs.index') }}" class="btn btn-secondary">История</a>
        <a href="{{ route('backup-batches.create') }}" class="btn btn-primary">Массовый бэкап</a>
    </div>
</div>

@php
    $hasActive = $activeBatches->isNotEmpty() || $standaloneRuns->isNotEmpty();
@endphp

<section class="mb-10" id="active-processes">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Сейчас запущено</h2>
        @if ($hasActive)
            <span class="badge bg-blue-100 text-blue-800">{{ $activeBatches->count() + $standaloneRuns->count() }} активн.</span>
        @endif
    </div>

    @if (! $hasActive)
        <div class="card p-5 text-sm text-slate-500">
            Нет активных бэкапов.
            <a href="{{ route('backup-batches.create') }}" class="text-brand-700 underline ml-1">Запустить очередь</a>
            ·
            <a href="{{ route('backup-runs.index') }}" class="text-brand-700 underline">История</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($activeBatches as $batch)
                @php
                    $current = $batch->currentItem;
                    $done = $batch->items->whereIn('status', ['completed', 'failed', 'skipped'])->count();
                    $total = $batch->items->count();
                @endphp
                <div class="card p-4 border-l-4 border-l-brand-500">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="font-semibold text-slate-900">Очередь #{{ $batch->id }}</span>
                                <span class="badge bg-blue-100 text-blue-800">{{ $batch->statusLabel() }}</span>
                                <span class="text-xs text-slate-400">{{ $batch->modeLabel() }}</span>
                            </div>
                            <p class="text-sm text-slate-600">
                                @if ($current?->server)
                                    Сейчас: <strong>{{ $current->server->name }}</strong>
                                    @if ($current->message)
                                        <span class="text-slate-400">· {{ \Illuminate\Support\Str::limit($current->message, 80) }}</span>
                                    @endif
                                @else
                                    {{ $batch->message ?: 'Ожидание…' }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ $done }}/{{ $total }} серверов · старт {{ $batch->started_at?->format('d.m H:i') ?? '—' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            @if ($current?->backup_run_id)
                                <a href="{{ route('backup-runs.show', $current->backup_run_id) }}" class="btn btn-primary !py-2 !text-sm">Лог текущего</a>
                            @endif
                            <a href="{{ route('backup-batches.show', $batch) }}" class="btn btn-secondary !py-2 !text-sm">Очередь</a>
                        </div>
                    </div>
                    @if ($batch->items->isNotEmpty())
                        <ul class="mt-3 pt-3 border-t border-slate-100 space-y-1.5">
                            @foreach ($batch->items as $item)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-700">
                                        <span class="@if($item->status === 'running') font-semibold @endif">{{ $item->server?->name }}</span>
                                        <span class="text-xs text-slate-400 ml-1">{{ $item->statusLabel() }}</span>
                                    </span>
                                    @if ($item->backup_run_id)
                                        <a href="{{ route('backup-runs.show', $item->backup_run_id) }}" class="text-brand-700 text-xs font-medium underline">лог</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            @foreach ($standaloneRuns as $run)
                <div class="card p-4 border-l-4 border-l-blue-400">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="font-semibold text-slate-900">Бэкап #{{ $run->id }}</span>
                                <span class="badge bg-blue-100 text-blue-800">running</span>
                            </div>
                            <p class="text-sm text-slate-600">{{ $run->server?->name }}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                @if ($run->remote_pid) PID {{ $run->remote_pid }} · @endif
                                с {{ $run->started_at?->format('d.m H:i:s') ?? '—' }}
                            </p>
                        </div>
                        <a href="{{ route('backup-runs.show', $run) }}" class="btn btn-primary !py-2 !text-sm">Открыть лог</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <div class="flex gap-2">
            <a href="{{ route('servers.index') }}" class="btn btn-secondary !py-2">Все серверы</a>
            <a href="{{ route('servers.create') }}" class="btn btn-secondary !py-2">+ Сервер</a>
        </div>
    </div>
    @forelse ($servers as $server)
        <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                    <div class="text-xs font-mono text-slate-400 mt-1">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</div>
                    <div class="text-sm mt-1.5" style="{{ $server->backupAgeStyle() }}">{{ $server->backupAgeLabel() }}</div>
                </div>
                <span class="text-xs shrink-0">
                    @if ($server->is_setup_complete)
                        <span class="badge badge-success">restic ок</span>
                    @else
                        <span class="badge badge-warning">restic не установлен</span>
                    @endif
                </span>
            </div>
        </a>
    @empty
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="{{ route('servers.create') }}" class="text-brand-700 underline">Добавьте сервер</a>
            или загрузите из Passtore на странице серверов.
        </div>
    @endforelse
</section>

@if ($servers->contains(fn ($s) => $s->readyForBackup()))
<section class="card p-6">
    <h2 class="section-title">Очередь бэкапов</h2>
    <p class="text-sm text-slate-500 mb-4">
        Выберите несколько SSH-хостов, режим (файлы / базы / оба) и запустите.
        Серверы обрабатываются <strong>строго по очереди</strong>; статус проверяется по SSH раз в 15 минут.
    </p>
    <a href="{{ route('backup-batches.create') }}" class="btn btn-primary">Выбрать серверы и запустить</a>
</section>
@endif

@if ($hasActive)
<script>
(function () {
    setTimeout(function () { location.reload(); }, 30000);
})();
</script>
@endif
@endsection
