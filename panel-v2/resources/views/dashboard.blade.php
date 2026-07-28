@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">Backaper v2 — настройка SSH/restic; бэкапы запускаются по CLI</p>
    </div>
    <a href="{{ route('guide') }}" class="text-sm text-brand-700 font-medium underline">Как пользоваться</a>
</div>

<section>
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <a href="{{ route('servers.create') }}" class="btn btn-primary !py-2">+ Сервер</a>
    </div>
    @forelse ($servers as $server)
        <a href="{{ route('servers.show', $server) }}" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3">
                <span class="font-semibold text-slate-900">{{ $server->name }}</span>
                <span class="text-xs text-slate-400">
                    {{ $server->kindLabel() }} ·
                    {{ $server->is_setup_complete ? 'restic ок' : 'нужна установка' }}
                </span>
            </div>
        </a>
    @empty
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="{{ route('servers.create') }}" class="text-brand-700 underline">Добавьте сервер</a>
            или прочитайте <a href="{{ route('guide') }}" class="underline">инструкцию</a>.
        </div>
    @endforelse
</section>
@endsection
