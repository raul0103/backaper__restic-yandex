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
                <p class="text-sm text-slate-500">
                    @if ($server->isVps())
                        Ищем MODX / WordPress / Laravel в <code>/var/www</code>, <code>/home</code>, <code>/srv</code> (до 6–8 уровней).
                    @else
                        Ищем конфиги сайтов в домашнем каталоге хостинга.
                    @endif
                </p>
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
                            <div class="font-medium text-slate-900 font-mono">{{ $db->database_name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                Хост: <span class="font-mono">{{ $db->database_server }}</span>
                                · пользователь: <span class="font-mono">{{ $db->database_user }}</span>
                                @if ($db->source)
                                    · {{ $db->source }}
                                @endif
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
