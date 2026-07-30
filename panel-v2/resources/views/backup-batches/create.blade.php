@extends('layouts.app')

@section('title', 'Очередь бэкапа')

@section('nav_map')
    <a href="{{ route('dashboard') }}">Панель</a>
    <span class="sep">→</span>
    <a href="{{ route('servers.index') }}">Серверы</a>
    <span class="sep">→</span>
    <strong class="text-slate-700">Очередь</strong>
    <span class="sep">→</span>
    <a href="{{ route('backup-runs.index') }}">История</a>
@endsection

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Очередь бэкапа</h1>
        <p class="page-subtitle">Серверы по одному. Быстрый запуск — на странице «Серверы».</p>
    </div>
    <a href="{{ route('servers.index') }}" class="btn btn-secondary">← К списку серверов</a>
</div>

@if ($activeBatch)
    <div class="alert alert-warning mb-6">
        Уже есть активная очередь
        <a href="{{ route('backup-batches.show', $activeBatch) }}" class="font-semibold underline">#{{ $activeBatch->id }}</a>
        ({{ $activeBatch->statusLabel() }}).
    </div>
@endif

<form method="post" action="{{ route('backup-batches.store') }}" class="space-y-6" id="batch-form">
    @csrf

    <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="section-title !mb-0">Серверы</h2>
            <div class="flex gap-2 text-sm">
                <button type="button" class="btn-ghost" id="select-all">Все готовые</button>
                <button type="button" class="btn-ghost" id="select-stale">Нужен бэкап</button>
                <button type="button" class="btn-ghost" id="select-none">Снять</button>
            </div>
        </div>

        @php $ready = $servers->filter(fn ($s) => $s->readyForBackup()); @endphp

        @forelse ($servers as $server)
            @php
                $ok = $server->readyForBackup();
                $checked = $ok && in_array($server->id, $preselected ?? [], true);
            @endphp
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 mb-2 {{ $ok ? 'hover:border-brand-500 cursor-pointer' : 'opacity-50' }} {{ $checked ? 'border-brand-500 bg-brand-50' : '' }}">
                <input
                    type="checkbox"
                    name="server_ids[]"
                    value="{{ $server->id }}"
                    class="mt-1 server-cb accent-teal-600"
                    data-ready="{{ $ok ? '1' : '0' }}"
                    data-days="{{ $server->daysSinceBackup() ?? 9999 }}"
                    @checked($checked)
                    @disabled(! $ok)
                >
                <span class="flex-1 min-w-0">
                    <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                    <span class="block text-xs text-slate-500 font-mono mt-0.5">
                        {{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}
                    </span>
                    <span class="block text-sm mt-1" style="{{ $server->backupAgeStyle() }}">{{ $server->backupAgeLabel() }}</span>
                </span>
                @if ($ok)
                    <span class="badge badge-success shrink-0">restic ок</span>
                @else
                    <span class="badge badge-warning shrink-0">нет restic</span>
                @endif
            </label>
        @empty
            <p class="text-sm text-slate-500">Серверов нет. <a href="{{ route('servers.index') }}" class="underline text-brand-700">Добавить</a>.</p>
        @endforelse

        @error('server_ids')
            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
        @enderror
    </section>

    <section class="card p-6">
        <h2 class="section-title">Режим</h2>
        <div class="space-y-3">
            @foreach ([
                'both' => ['Файлы + базы', 'restic + дампы БД'],
                'files' => ['Только файлы', 'restic snapshot'],
                'databases' => ['Только базы', 'mysqldump → rclone'],
            ] as $value => $labels)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="mode" value="{{ $value }}" class="mt-1 accent-teal-600" @checked(($prefillMode ?? 'both') === $value)>
                <span>
                    <span class="font-semibold">{{ $labels[0] }}</span>
                    <span class="block text-sm text-slate-500">{{ $labels[1] }}</span>
                </span>
            </label>
            @endforeach
        </div>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Интервал проверки</h2>
        <p class="text-sm text-slate-500 mb-4">Пока бэкап идёт, панель раз в N минут смотрит по SSH, закончился ли он — затем следующий сервер.</p>
        <label class="label" for="poll_minutes">Минуты</label>
        <input type="number" name="poll_minutes" id="poll_minutes" class="input max-w-[8rem]" value="15" min="1" max="120">
    </section>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn btn-primary" @disabled($ready->isEmpty())>Запустить очередь</button>
        <a href="{{ route('servers.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.server-cb'));
    document.getElementById('select-all')?.addEventListener('click', function () {
        boxes.forEach(function (cb) { if (cb.dataset.ready === '1') cb.checked = true; });
    });
    document.getElementById('select-none')?.addEventListener('click', function () {
        boxes.forEach(function (cb) { cb.checked = false; });
    });
    document.getElementById('select-stale')?.addEventListener('click', function () {
        boxes.forEach(function (cb) {
            if (cb.dataset.ready !== '1') return;
            cb.checked = parseInt(cb.dataset.days || '9999', 10) >= 20;
        });
    });
})();
</script>
@endsection
