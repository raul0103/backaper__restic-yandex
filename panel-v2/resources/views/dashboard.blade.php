@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">Backaper v2 — полный бэкап серверов и отдельные дампы БД</p>
    </div>
    <a href="{{ route('guide') }}" class="text-sm text-brand-700 font-medium underline">Как пользоваться</a>
</div>

<section class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <a href="{{ route('servers.create') }}" class="btn btn-primary !py-2">+ Сервер</a>
    </div>
    @forelse ($servers as $server)
        <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3">
                <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                <span class="text-xs text-slate-400">{{ $server->kindLabel() }} · путей {{ $server->backup_paths_count }} · баз {{ $server->databases_count }}</span>
            </div>
        </a>
    @empty
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="{{ route('servers.create') }}" class="text-brand-700 underline">Добавьте сервер</a>
            или прочитайте <a href="{{ route('guide') }}" class="underline">инструкцию</a>.
        </div>
    @endforelse
</section>

<section>
    <h2 class="section-title">Последние бэкапы</h2>
    @forelse ($recentRuns as $run)
        <a href="{{ route('backup-runs.show', $run) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3 text-sm">
                <span>
                    {{ $run->server->name }}
                </span>
                <span class="text-slate-400">{{ $run->status }} · {{ $run->created_at?->diffForHumans() }}</span>
            </div>
        </a>
    @empty
        <p class="text-sm text-slate-400">Бэкапов ещё не было</p>
    @endforelse
</section>
@endsection
