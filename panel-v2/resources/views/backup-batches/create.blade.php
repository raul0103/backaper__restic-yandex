@extends('layouts.app')

@section('title', 'Массовый бэкап')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Массовый бэкап</h1>
        <p class="page-subtitle">Выберите SSH-серверы — очередь пойдёт по одному, чтобы не грузить Яндекс.Диск</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">← Панель</a>
</div>

@if ($activeBatch)
    <div class="alert alert-warning mb-6">
        Уже есть активная очередь
        <a href="{{ route('backup-batches.show', $activeBatch) }}" class="font-semibold underline">#{{ $activeBatch->id }}</a>
        ({{ $activeBatch->statusLabel() }}). Можно запустить ещё одну — они независимы, но нагрузка на Диск вырастет.
    </div>
@endif

<form method="post" action="{{ route('backup-batches.store') }}" class="space-y-6" id="batch-form">
    @csrf

    <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="section-title !mb-0">Серверы (SSH)</h2>
            <div class="flex gap-2 text-sm">
                <button type="button" class="btn-ghost" id="select-all">Выбрать все готовые</button>
                <button type="button" class="btn-ghost" id="select-none">Снять</button>
            </div>
        </div>

        @php $ready = $servers->filter(fn ($s) => $s->readyForBackup()); @endphp

        @forelse ($servers as $server)
            @php $ok = $server->readyForBackup(); @endphp
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 mb-2 {{ $ok ? 'hover:border-brand-500 cursor-pointer' : 'opacity-50' }}">
                <input
                    type="checkbox"
                    name="server_ids[]"
                    value="{{ $server->id }}"
                    class="mt-1 server-cb"
                    data-ready="{{ $ok ? '1' : '0' }}"
                    @disabled(! $ok)
                >
                <span class="flex-1 min-w-0">
                    <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                    <span class="block text-xs text-slate-500 font-mono mt-0.5">
                        {{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}
                        · {{ $server->kindLabel() }}
                    </span>
                </span>
                @if ($ok)
                    <span class="badge badge-success shrink-0">restic ок</span>
                @else
                    <span class="badge badge-warning shrink-0">нужна установка</span>
                @endif
            </label>
        @empty
            <p class="text-sm text-slate-500">Серверов нет. <a href="{{ route('servers.create') }}" class="underline text-brand-700">Добавьте</a>.</p>
        @endforelse

        @if ($ready->isEmpty() && $servers->isNotEmpty())
            <p class="text-sm text-amber-700 mt-3">Нет серверов с установленным restic — сначала завершите установку.</p>
        @endif
        @error('server_ids')
            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
        @enderror
    </section>

    <section class="card p-6">
        <h2 class="section-title">Что бэкапить</h2>
        <div class="space-y-3">
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="mode" value="both" class="mt-1" checked>
                <span>
                    <span class="font-semibold">Файлы + базы</span>
                    <span class="block text-sm text-slate-500">restic + дампы БД (из панели или поиск конфигов на сервере)</span>
                </span>
            </label>
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="mode" value="files" class="mt-1">
                <span>
                    <span class="font-semibold">Только файлы</span>
                    <span class="block text-sm text-slate-500">restic snapshot</span>
                </span>
            </label>
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="mode" value="databases" class="mt-1">
                <span>
                    <span class="font-semibold">Только базы</span>
                    <span class="block text-sm text-slate-500">mysqldump → rclone (поиск конфигов на лету, если в панели пусто)</span>
                </span>
            </label>
        </div>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Очередь</h2>
        <p class="text-sm text-slate-500 mb-4">
            Серверы идут <strong>строго по одному</strong>. Пока бэкап на текущем не закончится —
            следующий не стартует. Панель подключается по SSH и проверяет статус раз в N минут.
        </p>
        <label class="label" for="poll_minutes">Интервал проверки (минуты)</label>
        <input type="number" name="poll_minutes" id="poll_minutes" class="input max-w-[8rem]" value="15" min="1" max="120">
    </section>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn btn-primary" @disabled($ready->isEmpty())>
            Запустить очередь
        </button>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.server-cb'));
    document.getElementById('select-all')?.addEventListener('click', function () {
        boxes.forEach(function (cb) {
            if (cb.dataset.ready === '1') cb.checked = true;
        });
    });
    document.getElementById('select-none')?.addEventListener('click', function () {
        boxes.forEach(function (cb) { cb.checked = false; });
    });
})();
</script>
@endsection
