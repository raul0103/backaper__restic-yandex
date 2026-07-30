@extends('layouts.app')

@section('title', 'Шаг 2 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 2, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 2 — Установка</h1>
    <p class="page-subtitle">Поставим на сервер restic, rclone и скрипты бэкапа. Дальше всё запускается по SSH.</p>
</div>

<div class="help-box mb-6">
    <strong>Что произойдёт:</strong> по SSH обновятся restic и rclone, заново создастся репозиторий на Яндекс.Диске
    (<code>restic-repo/…</code> и папка <code>databases/…</code>), зальются CLI-скрипты.
    Нужно, если удалили эти папки на Диске или сменили пароль / токен / имя папки.
</div>

<div class="card p-6 space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-600">Статус:</span>
        @if ($server->is_setup_complete)
            <span class="badge badge-success">Установлено</span>
        @else
            <span class="badge badge-warning">Ещё не установлено</span>
        @endif
    </div>

    <form method="post" action="{{ route('servers.wizard.install.run', $server) }}" class="flex flex-wrap gap-3">
        @csrf
        <button type="submit" class="btn btn-primary">
            {{ $server->is_setup_complete ? 'Переустановить' : 'Установить на сервер' }}
        </button>
        <a href="{{ route('servers.wizard.connect', $server) }}" class="btn btn-secondary">← Назад</a>
        @if ($server->is_setup_complete)
            <a href="{{ route('servers.show', $server) }}" class="btn btn-secondary">К серверу →</a>
        @endif
    </form>

    @if ($server->setup_log)
        <details class="mt-2">
            <summary class="text-sm text-slate-500 cursor-pointer">Лог ошибки</summary>
            <pre class="log-block mt-2 text-xs">{{ $server->setup_log }}</pre>
        </details>
    @endif
</div>

<form method="post" action="{{ route('servers.destroy', $server) }}" class="mt-4" onsubmit="return confirm('Удалить «{{ $server->name }}» из панели?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-secondary !text-red-600 !border-red-200 hover:!bg-red-50">Удалить сервер</button>
</form>
@endsection
