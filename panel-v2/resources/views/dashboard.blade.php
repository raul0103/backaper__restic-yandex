@extends('layouts.app')

@section('title', 'Панель')

@section('nav_map')
    <strong class="text-slate-700">Панель</strong>
    <span class="sep">→</span>
    <a href="{{ route('servers.index') }}">Серверы</a>
    <span class="sep">→</span>
    <a href="{{ route('backup-batches.create') }}">Очередь</a>
    <span class="sep">→</span>
    <a href="{{ route('backup-runs.index') }}">История</a>
@endsection

@section('content')
@php
    $hasActive = $activeBatches->isNotEmpty() || $standaloneRuns->isNotEmpty();
    $readyCount = $servers->filter(fn ($s) => $s->readyForBackup())->count();
    $needBackup = $servers->filter(function ($s) {
        if (! $s->readyForBackup()) return false;
        $days = $s->daysSinceBackup();
        return $days === null || $days >= 20;
    })->count();
@endphp

<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">Сводка и активные бэкапы. Запуск — со страницы «Серверы».</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('servers.index') }}" class="btn btn-primary">Выбрать серверы</a>
        <a href="{{ route('backup-runs.index') }}" class="btn btn-secondary">История</a>
    </div>
</div>

<section class="grid sm:grid-cols-3 gap-3 mb-10">
    <a href="{{ route('servers.index') }}" class="card card-hover p-4 no-underline text-inherit">
        <div class="text-xs text-slate-500 font-medium">Серверы</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $servers->count() }}</div>
        <div class="text-xs text-slate-400 mt-1">restic готов: {{ $readyCount }}</div>
    </a>
    <a href="{{ route('servers.index') }}#select-stale" class="card card-hover p-4 no-underline text-inherit">
        <div class="text-xs text-slate-500 font-medium">Нужен бэкап</div>
        <div class="text-2xl font-bold {{ $needBackup > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-1">{{ $needBackup }}</div>
        <div class="text-xs text-slate-400 mt-1">нет бэкапа или &gt; 20 дней</div>
    </a>
    <a href="{{ $hasActive && $activeBatches->first() ? route('backup-batches.show', $activeBatches->first()) : route('backup-batches.create') }}" class="card card-hover p-4 no-underline text-inherit">
        <div class="text-xs text-slate-500 font-medium">Очередь</div>
        <div class="text-2xl font-bold {{ $hasActive ? 'text-blue-600' : 'text-slate-900' }} mt-1">
            {{ $activeBatches->count() + $standaloneRuns->count() }}
        </div>
        <div class="text-xs text-slate-400 mt-1">{{ $hasActive ? 'сейчас активно' : 'ничего не запущено' }}</div>
    </a>
</section>

<section class="card p-5 mb-10">
    <h2 class="section-title !mb-3">Как пользоваться</h2>
    <ol class="text-sm text-slate-600 space-y-2 list-decimal pl-5">
        <li><a href="{{ route('servers.index') }}" class="text-brand-700 font-medium underline">Серверы</a> — отметьте хосты чекбоксами (или «Нужен бэкап»).</li>
        <li>Внизу выберите режим (файлы / базы / оба) и нажмите <strong>Запустить очередь</strong>.</li>
        <li>Серверы идут <strong>по одному</strong>. Следите здесь или в <a href="{{ route('backup-runs.index') }}" class="text-brand-700 underline">Истории</a>.</li>
    </ol>
</section>

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
            <a href="{{ route('servers.index') }}" class="text-brand-700 underline ml-1">Выбрать серверы и запустить</a>
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
                                <a href="{{ route('backup-runs.show', $current->backup_run_id) }}" class="btn btn-primary !py-2 !text-sm">Лог</a>
                            @endif
                            <a href="{{ route('backup-batches.show', $batch) }}" class="btn btn-secondary !py-2 !text-sm">Очередь</a>
                        </div>
                    </div>
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
                        </div>
                        <a href="{{ route('backup-runs.show', $run) }}" class="btn btn-primary !py-2 !text-sm">Лог</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section>
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы (кратко)</h2>
        <a href="{{ route('servers.index') }}" class="btn btn-secondary !py-2">Все и запуск →</a>
    </div>
    @forelse ($servers->take(8) as $server)
        <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-3 mb-2 no-underline text-inherit">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                    <span class="text-sm ml-2" style="{{ $server->backupAgeStyle() }}">{{ $server->backupAgeLabel() }}</span>
                </div>
                @if ($server->is_setup_complete)
                    <span class="badge badge-success">restic</span>
                @else
                    <span class="badge badge-warning">нет restic</span>
                @endif
            </div>
        </a>
    @empty
        <div class="card p-5 text-sm text-slate-500">Нет серверов. <a href="{{ route('servers.index') }}" class="underline text-brand-700">Добавить / Passtore</a></div>
    @endforelse
    @if ($servers->count() > 8)
        <p class="text-sm text-slate-400 mt-2"><a href="{{ route('servers.index') }}" class="underline">Ещё {{ $servers->count() - 8 }} →</a></p>
    @endif
</section>

@if ($hasActive)
<script>
setTimeout(function () { location.reload(); }, 30000);
</script>
@endif
@endsection
