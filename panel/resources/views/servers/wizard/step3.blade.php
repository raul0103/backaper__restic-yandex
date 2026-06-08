@extends('layouts.app')

@section('title', 'Шаг 3 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 3, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 3 — Базы данных</h1>
    <p class="page-subtitle">Данные подключения из найденных <code class="text-brand-600 bg-brand-50 px-1 rounded text-sm">config.inc.php</code></p>
</div>

<div class="flex flex-wrap gap-3 mb-8">
    <form method="post" action="{{ route('servers.wizard.extract-databases', $server) }}">
        @csrf
        <button type="submit" class="btn btn-violet">Извлечь базы из конфигов</button>
    </form>
    <a href="{{ route('servers.wizard.step2', $server) }}" class="btn btn-secondary">← Назад</a>
</div>

@if ($server->modxConfigs->isEmpty())
    <div class="card p-6 text-slate-500">
        Нет конфигов. <a href="{{ route('servers.wizard.step2', $server) }}" class="text-brand-600 font-medium hover:underline">Вернитесь на шаг 2</a>.
    </div>
@else
    <div class="card overflow-hidden mb-8">
        <div class="table-wrap p-4 sm:p-6">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Конфиг</th>
                        <th>База данных</th>
                        <th>Пользователь</th>
                        <th>Prefix</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($server->modxConfigs as $config)
                        <tr>
                            <td class="font-mono text-xs">{{ basename($config->config_path) }}</td>
                            <td>
                                @if ($config->database)
                                    <span class="font-medium text-brand-700">{{ $config->database->database_name }}</span>
                                    <span class="text-slate-400 text-xs block">@ {{ $config->database->database_server }}</span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td>{{ $config->database?->database_user ?? '—' }}</td>
                            <td><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $config->database?->table_prefix ?? '—' }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($server->projectDatabases()->exists())
        <form method="post" action="{{ route('servers.wizard.step4.go', $server) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Далее: проекты →</button>
        </form>
    @endif
@endif
@endsection
