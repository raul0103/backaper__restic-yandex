@extends('layouts.app')

@section('title', 'Серверы')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="page-title">Серверы</h1>
        <p class="page-subtitle">VPS и хостинги для бэкапа</p>
    </div>
    <a href="{{ route('servers.create') }}" class="btn btn-primary">+ Сервер</a>
</div>

@forelse ($servers as $server)
    <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-5 mb-3 no-underline text-inherit">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $server->name }}</div>
                <div class="text-xs text-slate-400 mt-1">
                    <span class="badge badge-info">{{ $server->kindLabel() }}</span>
                    <span class="ml-2 font-mono">{{ $server->host }}</span>
                </div>
            </div>
            <div class="text-sm text-slate-500">
                путей {{ $server->backup_paths_count }} · баз {{ $server->databases_count }}
            </div>
        </div>
    </a>
@empty
    <div class="card p-8 text-center">
        <p class="text-slate-500 mb-4">Серверов пока нет</p>
        <a href="{{ route('servers.create') }}" class="btn btn-primary">Добавить сервер</a>
        <p class="text-xs text-slate-400 mt-4"><a href="{{ route('guide') }}" class="underline">Как пользоваться</a></p>
    </div>
@endforelse
@endsection
