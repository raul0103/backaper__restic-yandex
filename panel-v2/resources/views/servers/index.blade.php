@extends('layouts.app')

@section('title', 'Серверы')

@section('nav_map')
    <a href="{{ route('dashboard') }}">Панель</a>
    <span class="sep">→</span>
    <strong class="text-slate-700">Серверы</strong>
    <span class="sep">→</span>
    <a href="{{ route('backup-batches.create') }}">Очередь</a>
    <span class="sep">→</span>
    <a href="{{ route('backup-runs.index') }}">История</a>
@endsection

@section('main_class', 'pb-28')

@section('content')
@php
    $readyCount = $servers->filter(fn ($s) => $s->readyForBackup())->count();
@endphp

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="page-title">Серверы</h1>
        <p class="page-subtitle">
            Отметьте нужные → режим → «В очередь».
            Одновременно одна очередь; следующие ждут.
            Готовых к бэкапу: <strong>{{ $readyCount }}</strong> из {{ $servers->count() }}
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($passtoreConfigured)
            <form method="post" action="{{ route('servers.sync-passtore') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Из Passtore</button>
            </form>
        @endif
        <a href="{{ route('servers.create') }}" class="btn btn-primary">+ Сервер</a>
    </div>
</div>

@if ($servers->isNotEmpty())
<div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
    <button type="button" class="btn-ghost" id="select-ready">Выбрать готовые</button>
    <button type="button" class="btn-ghost" id="select-none">Снять все</button>
    <button type="button" class="btn-ghost" id="select-stale" title="Без бэкапа или старше 20 дней">Нужен бэкап</button>
    <span class="text-slate-400 ml-auto text-xs" id="selection-hint">0 выбрано</span>
</div>
<p class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mb-4">
    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-1 h-3 rounded-sm" style="background:#8b5cf6"></span> VPS</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-1 h-3 rounded-sm" style="background:#14b8a6"></span> Хостинг</span>
</p>
@endif

<form method="post" action="{{ route('backup-batches.store') }}" id="quick-queue-form">
    @csrf
    <input type="hidden" name="poll_minutes" value="15">

    @forelse ($servers as $server)
        @php
            $ok = $server->readyForBackup();
            $inQueue = in_array($server->id, $queuedServerIds ?? [], true);
            $canQueue = $ok && ! $inQueue;
        @endphp
        <div class="card server-row mb-2 p-4 {{ $server->isVps() ? 'server-kind-vps' : 'server-kind-hosting' }} {{ $canQueue ? '' : 'opacity-70' }}" data-server-row data-ready="{{ $canQueue ? '1' : '0' }}" data-days="{{ $server->daysSinceBackup() ?? 9999 }}">
            <div class="flex flex-wrap items-start gap-3">
                <label class="pt-1 {{ $canQueue ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                    <input
                        type="checkbox"
                        name="server_ids[]"
                        value="{{ $server->id }}"
                        class="server-cb w-4 h-4 accent-teal-600"
                        data-ready="{{ $canQueue ? '1' : '0' }}"
                        @disabled(! $canQueue)
                    >
                </label>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('servers.show', $server) }}" class="text-lg font-semibold text-slate-900 no-underline hover:text-brand-700">
                            {{ $server->name }}
                        </a>
                        @if ($inQueue)
                            <span class="badge bg-blue-100 text-blue-800">в очереди</span>
                        @endif
                        @unless ($server->is_setup_complete)
                            <span class="badge badge-warning">restic не установлен</span>
                        @endunless
                    </div>
                    <div class="text-sm mt-2" style="{{ $server->backupAgeStyle() }}">
                        {{ $server->backupAgeLabel() }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 shrink-0 items-center">
                    @if ($canQueue)
                        <button
                            type="button"
                            class="btn btn-primary !py-1.5 !text-xs"
                            data-queue-one="{{ $server->id }}"
                        >В очередь</button>
                    @elseif ($inQueue)
                        <span class="text-xs text-blue-700">Уже в очереди</span>
                    @elseif (! $server->is_setup_complete)
                        <a href="{{ route($server->wizardRoute(), $server) }}" class="btn btn-secondary !py-1.5 !text-xs">Установить</a>
                    @endif
                </div>
            </div>
        </div>
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

    @if ($servers->isNotEmpty())
    <div class="queue-bar -mx-4 sm:-mx-6 mt-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex flex-wrap items-center gap-3">
            <div class="text-sm text-slate-600">
                <strong id="selected-count">0</strong> в очереди
            </div>
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="mode" value="both" checked class="accent-teal-600"> Файлы+БД
                </label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="mode" value="files" class="accent-teal-600"> Файлы
                </label>
                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" name="mode" value="databases" class="accent-teal-600"> Базы
                </label>
            </div>
            <div class="flex gap-2 ml-auto">
                <a href="{{ route('backup-batches.create') }}" class="btn btn-secondary !py-2">Расширенно…</a>
                <button type="submit" class="btn btn-primary !py-2" id="queue-submit" disabled>
                    Запустить очередь
                </button>
            </div>
        </div>
    </div>
    @endif
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('quick-queue-form');
    if (!form) return;

    const boxes = Array.from(form.querySelectorAll('.server-cb'));
    const countEl = document.getElementById('selected-count');
    const hintEl = document.getElementById('selection-hint');
    const submitBtn = document.getElementById('queue-submit');

    function sync() {
        const selected = boxes.filter(function (cb) { return cb.checked; });
        const n = selected.length;
        if (countEl) countEl.textContent = String(n);
        if (hintEl) hintEl.textContent = n + ' выбрано';
        if (submitBtn) submitBtn.disabled = n === 0;

        form.querySelectorAll('[data-server-row]').forEach(function (row) {
            const cb = row.querySelector('.server-cb');
            row.classList.toggle('selected', !!(cb && cb.checked));
        });
    }

    boxes.forEach(function (cb) {
        cb.addEventListener('change', sync);
    });

    document.getElementById('select-ready')?.addEventListener('click', function () {
        boxes.forEach(function (cb) { cb.checked = cb.dataset.ready === '1'; });
        sync();
    });
    document.getElementById('select-none')?.addEventListener('click', function () {
        boxes.forEach(function (cb) { cb.checked = false; });
        sync();
    });
    document.getElementById('select-stale')?.addEventListener('click', function () {
        form.querySelectorAll('[data-server-row]').forEach(function (row) {
            const cb = row.querySelector('.server-cb');
            if (!cb || cb.disabled) return;
            const days = parseInt(row.dataset.days || '9999', 10);
            cb.checked = days >= 20;
        });
        sync();
    });

    document.querySelectorAll('[data-queue-one]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = String(btn.getAttribute('data-queue-one'));
            boxes.forEach(function (cb) {
                cb.checked = String(cb.value) === id;
            });
            const both = form.querySelector('input[name="mode"][value="both"]');
            if (both && !form.querySelector('input[name="mode"]:checked')) {
                both.checked = true;
            }
            sync();
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', function (e) {
        const n = boxes.filter(function (cb) { return cb.checked; }).length;
        if (n === 0) {
            e.preventDefault();
            alert('Выберите хотя бы один сервер с установленным restic');
        }
    });

    sync();
})();
</script>
@endpush
