@extends('layouts.app')

@section('title', $server->name)

@section('content')
<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6 mb-8">
    <div>
        <h1 class="page-title">{{ $server->name }}</h1>
        <p class="page-subtitle">{{ $server->ssh_user.'@'.$server->host }}:{{ $server->ssh_port }}</p>
        <p class="text-sm text-slate-500 mt-2">
            Бэкапы:
            <code class="text-xs bg-brand-50 text-brand-700 px-1.5 py-0.5 rounded">{{ $server->repoSlug() }}</code>
            · файлы в restic, БД в
            <code class="text-xs">{{ $server->cloudPrefix() }}/databases/</code>
        </p>
    </div>
    <div class="flex flex-wrap gap-2 shrink-0">
        <a href="{{ route('servers.wizard.step1', $server) }}" class="btn btn-secondary">SSH и облако</a>
        @unless($server->isWizardComplete())
            <a href="{{ route($server->wizardRoute(), $server) }}" class="btn btn-secondary">Мастер →</a>
        @endunless
        @if($server->readyForRemoteSetup())
            <form method="post" action="{{ route('servers.setup', $server) }}">@csrf<button type="submit" class="btn btn-blue">{{ $server->is_setup_complete ? 'Переустановить restic' : 'Установить restic' }}</button></form>
        @endif
        @if($server->readyForBackup())
            <form method="post" action="{{ route('servers.backup', $server) }}">@csrf<button type="submit" class="btn btn-primary">Бэкап всех</button></form>
        @endif
        @if($server->is_setup_complete)
            <a href="{{ route('servers.restore', $server) }}" class="btn btn-secondary">Восстановление</a>
        @endif
    </div>
</div>

@if(!$server->is_setup_complete)
<section class="callout-amber mb-8">
    <h2 class="font-semibold text-amber-900 mb-2">Осталось установить restic</h2>
    <ol class="text-sm text-amber-950 space-y-1.5 list-decimal list-inside mb-0">
        <li>На <a href="{{ route('servers.wizard.step1', $server) }}" class="text-brand-700 font-medium hover:underline">шаге 1</a> — токен Яндекс.Диска и имя папки бэкапов → Сохранить.</li>
        <li>Кнопка <strong>«Установить restic»</strong> выше (проекты не обязательны).</li>
    </ol>
    @if(empty($server->rclone_token))
        <p class="text-sm text-red-700 mt-3 mb-0">Нет токена Яндекс.Диска — добавьте на шаге 1.</p>
    @endif
</section>
@elseif(!$server->isWizardComplete())
<section class="callout-amber mb-8">
    <p class="text-sm text-amber-950 mb-0">
        Restic готов. Проекты можно добавить через
        <a href="{{ route($server->wizardRoute(), $server) }}" class="text-brand-700 font-medium hover:underline">мастер</a>
        или после восстановления сайта.
    </p>
</section>
@endif

<div class="grid sm:grid-cols-3 gap-4 mb-8">
    <div class="card stat-card">
        <div class="stat-label">Restic</div>
        <div class="stat-value">
            @if($server->is_setup_complete)
                <span class="text-brand-600">Готов</span>
            @else
                <span class="text-amber-600">Не установлен</span>
            @endif
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Проектов</div>
        <div class="stat-value">{{ $server->projects->where('is_enabled', true)->count() }}</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Конфигов MODX</div>
        <div class="stat-value">{{ $server->modxConfigs()->count() }}</div>
    </div>
</div>

@if ($server->setup_log && !$server->is_setup_complete)
<section class="mb-8">
    <h2 class="section-title">Лог установки</h2>
    <pre class="log-block">{{ $server->setup_log }}</pre>
</section>
@endif

<section class="mb-10">
    <h2 class="section-title">Проекты</h2>
    @forelse ($server->projects as $project)
        <div class="card p-5 mb-3">
            <div class="flex flex-wrap justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-slate-900">{{ $project->name }}</span>
                        @unless($project->is_enabled)
                            <span class="badge badge-warning">выкл</span>
                        @endunless
                    </div>
                    <div class="text-sm text-slate-500 font-mono mt-1 truncate">{{ $project->root_path }}</div>
                    <div class="text-xs text-slate-400 mt-2">{{ $project->modxConfig?->config_path }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        БД: {{ $project->database?->database_name }} · {{ $project->database?->table_prefix }}session
                    </div>
                    @if($project->exclusionRules->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach($project->exclusionRules as $rule)
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">{{ $rule->pattern }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex gap-3 items-start shrink-0">
                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-ghost">Изменить</a>
                    <form method="post" action="{{ route('projects.backup', $project) }}">@csrf<button type="submit" class="btn btn-ghost !text-blue-600">Бэкап</button></form>
                </div>
            </div>
        </div>
    @empty
        <div class="card p-6 text-slate-500 text-sm">Проекты не настроены</div>
    @endforelse
</section>

<section>
    <h2 class="section-title">История бэкапов</h2>
    @forelse ($server->backupRuns as $run)
        <a href="{{ route('backup-runs.show', $run) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between items-center gap-2 text-sm">
                <span class="text-slate-700">
                    {{ $run->created_at?->format('d.m.Y H:i') }}
                    @if($run->project) · {{ $run->project->name }} @else · все проекты @endif
                </span>
                @if($run->status === 'completed')
                    <span class="badge badge-success">{{ $run->status }}</span>
                @elseif($run->status === 'running')
                    <span class="badge bg-blue-100 text-blue-800">{{ $run->status }}</span>
                @else
                    <span class="badge badge-error">{{ $run->status }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="card p-6 text-slate-500 text-sm">Пусто</div>
    @endforelse
</section>

<form method="post" action="{{ route('servers.destroy', $server) }}" class="mt-10 pt-6 border-t border-slate-200" onsubmit="return confirm('Удалить сервер?')">
    @csrf @method('DELETE')
    <button type="submit" class="btn btn-danger">Удалить сервер</button>
</form>
@endsection
