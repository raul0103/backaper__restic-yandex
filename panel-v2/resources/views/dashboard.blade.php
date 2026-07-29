@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">SSH-серверы и очередь массовых бэкапов (по одному, без перегрузки Яндекса)</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('guide') }}" class="text-sm text-brand-700 font-medium underline self-center">Как пользоваться</a>
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

@if ($recentRuns->isNotEmpty())
<section class="mb-10">
    <h2 class="section-title">Недавние</h2>
    <div class="card divide-y divide-slate-100 overflow-hidden">
        @foreach ($recentRuns as $run)
            <a href="{{ route('backup-runs.show', $run) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 no-underline text-inherit hover:bg-slate-50">
                <div>
                    <span class="font-medium text-slate-900">#{{ $run->id }}</span>
                    <span class="text-slate-600 text-sm ml-1">{{ $run->server?->name }}</span>
                    <span class="block text-xs text-slate-400 mt-0.5">{{ $run->finished_at?->format('d.m.Y H:i') }}</span>
                </div>
                @if ($run->status === 'completed')
                    <span class="badge badge-success">готово</span>
                @else
                    <span class="badge badge-error">ошибка</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <a href="{{ route('servers.create') }}" class="btn btn-secondary !py-2">+ Сервер</a>
    </div>
    @forelse ($servers as $server)
        <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3">
                <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                <span class="text-xs text-slate-400">
                    {{ $server->kindLabel() }} ·
                    {{ $server->is_setup_complete ? 'restic ок' : 'нужна установка' }}
                </span>
            </div>
            <div class="text-xs font-mono text-slate-400 mt-1">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</div>
        </a>
    @empty
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="{{ route('servers.create') }}" class="text-brand-700 underline">Добавьте сервер</a>
            или прочитайте <a href="{{ route('guide') }}" class="underline">инструкцию</a>.
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
    // Лёгкое обновление страницы, пока есть активные процессы
    setTimeout(function () { location.reload(); }, 30000);
})();
</script>
@endif
@endsection
