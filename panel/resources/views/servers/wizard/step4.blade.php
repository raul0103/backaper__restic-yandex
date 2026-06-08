@extends('layouts.app')

@section('title', 'Шаг 4 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 4, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 4 — Проекты и исключения</h1>
    <p class="page-subtitle">Каждый конфиг + база = проект. Укажите путь к сайту и папки, которые не бэкапить.</p>
</div>

<form method="post" action="{{ route('servers.wizard.step4.finish', $server) }}" class="space-y-6">
    @csrf

    @foreach ($server->modxConfigs as $config)
        @if ($config->database)
            @php
                $project = $config->project;
                $enabled = old('projects.'.$config->id.'.enabled', $project?->is_enabled ?? true);
                $exclusionLines = $project?->exclusionRules?->pluck('pattern')->all() ?? [];
                $exclusionsValue = old('projects.'.$config->id.'.exclusions', implode("\n", $exclusionLines));
            @endphp

            <fieldset class="card p-5 sm:p-6">
                <div class="flex items-start gap-3 mb-5">
                    <input type="checkbox"
                           name="projects[{{ $config->id }}][enabled]"
                           value="1"
                           class="mt-1 w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                           @if($enabled) checked @endif>
                    <div class="flex-1 min-w-0">
                        <legend class="font-semibold text-slate-900">{{ $config->label ?? basename($config->config_path) }}</legend>
                        <p class="text-xs font-mono text-slate-400 mt-1 truncate">{{ $config->config_path }}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            БД: <span class="font-medium">{{ $config->database->database_name }}</span>
                            · {{ $config->database->database_user.'@'.$config->database->database_server }}
                        </p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="label">Название проекта</label>
                        <input name="projects[{{ $config->id }}][name]"
                               value="{{ old('projects.'.$config->id.'.name', $project?->name ?? $config->label) }}"
                               required
                               class="input">
                    </div>
                    <div>
                        <label class="label">Путь к проекту (корень сайта)</label>
                        <input name="projects[{{ $config->id }}][root_path]"
                               value="{{ old('projects.'.$config->id.'.root_path', $project?->root_path ?? $config->suggested_root_path) }}"
                               required
                               class="input font-mono text-sm">
                    </div>
                </div>

                <div>
                    <label class="label">Исключения (по одному на строку)</label>
                    <textarea name="projects[{{ $config->id }}][exclusions]"
                              rows="4"
                              class="textarea font-mono text-sm">{{ $exclusionsValue }}</textarea>
                    <p class="text-xs text-slate-400 mt-1.5">Всегда исключаются: core/cache, core/packages, node_modules, .git</p>
                </div>
            </fieldset>
        @endif
    @endforeach

    <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="btn btn-primary">Завершить настройку</button>
        <a href="{{ route('servers.wizard.step3', $server) }}" class="btn btn-secondary">← Назад</a>
    </div>
</form>
@endsection
