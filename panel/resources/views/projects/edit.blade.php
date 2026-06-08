@extends('layouts.app')

@section('title', 'Проект '.$project->name)

@section('content')
<div class="mb-8">
    <h1 class="page-title">{{ $project->name }}</h1>
    <p class="page-subtitle font-mono text-sm">{{ $project->root_path }}</p>
</div>

<div class="card p-6 sm:p-8 max-w-2xl">
    <form method="post" action="{{ route('projects.update', $project) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="label">Название</label>
            <input name="name" value="{{ old('name', $project->name) }}" class="input">
        </div>

        <div>
            <label class="label">Путь к проекту</label>
            <input name="root_path" value="{{ old('root_path', $project->root_path) }}" class="input font-mono text-sm">
        </div>

        <div>
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $project->is_enabled))
                       class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm font-medium text-slate-700">Включён в бэкап</span>
            </label>
        </div>

        <div>
            <label class="label">Исключения (по одному на строку)</label>
            <textarea name="exclusions" rows="8" class="textarea font-mono text-sm">{{ old('exclusions', $project->exclusionRules->pluck('pattern')->join("\n")) }}</textarea>
        </div>

        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm space-y-1.5 text-slate-600">
            <div><span class="text-slate-400">config:</span> <code class="text-xs">{{ $project->modxConfig?->config_path }}</code></div>
            <div><span class="text-slate-400">БД:</span> {{ $project->database?->database_user }}@{{ $project->database?->database_server }}/{{ $project->database?->database_name }}</div>
            <div><span class="text-slate-400">session:</span> {{ $project->sessionTable() }}</div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('servers.show', $project->server_id) }}" class="btn btn-secondary">Назад</a>
        </div>
    </form>
</div>
@endsection
