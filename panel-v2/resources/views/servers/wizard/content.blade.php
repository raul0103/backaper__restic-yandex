@extends('layouts.app')

@section('title', 'Шаг 3 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 3, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 3 — Базы данных</h1>
    <p class="page-subtitle">Файлы бэкапятся целиком автоматически. Здесь только дампы БД.</p>
</div>

<div class="help-box mb-6">
    @if ($server->isHosting())
        <strong>Файлы:</strong> весь аккаунт хостинга (домашняя папка пользователя).
    @else
        <strong>Файлы:</strong> весь сервер (<code>/</code>), без системных каталогов вроде /proc, /sys, /tmp.
    @endif
    <br>
    Из сайтов пропускаются: cache, node_modules, .git, vendor.
    Пути указывать не нужно.
</div>

<form method="post" action="{{ route('servers.wizard.content.finish', $server) }}" class="space-y-6">
    @csrf

    <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="section-title !mb-1">Базы данных (отдельные дампы)</h2>
                <p class="text-sm text-slate-500">Найдите конфиги сайтов и отметьте, какие базы сохранять отдельно.</p>
            </div>
            <button type="submit" formaction="{{ route('servers.wizard.discover-databases', $server) }}" class="btn btn-secondary">Найти базы</button>
        </div>

        @if ($server->databases->isEmpty())
            <p class="text-sm text-slate-400">Баз пока нет. Нажмите «Найти базы» — или завершите настройку: файлы всё равно будут бэкапиться.</p>
        @else
            <div class="space-y-2">
                @foreach ($server->databases as $db)
                    <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50">
                        <input type="checkbox" name="databases[{{ $db->id }}][enabled]" value="1" @checked($db->is_enabled) class="mt-1 w-4 h-4">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900">{{ $db->displayName() }}</div>
                            <div class="text-xs text-slate-500 font-mono truncate">
                                {{ $db->database_user }}@{{ $db->database_server }} / {{ $db->database_name }}
                                @if ($db->source) · {{ $db->source }} @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        @endif
    </section>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn btn-primary">Завершить настройку</button>
        <a href="{{ route('servers.wizard.install', $server) }}" class="btn btn-secondary">← Назад</a>
    </div>
</form>
@endsection
