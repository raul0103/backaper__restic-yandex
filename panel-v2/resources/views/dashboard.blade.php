@extends('layouts.app')

@section('title', 'Панель')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">SSH-серверы и очередь массовых бэкапов (по одному, без перегрузки Яндекса)</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('guide') }}" class="text-sm text-brand-700 font-medium underline self-center">Как пользоваться</a>
        <a href="{{ route('backup-batches.create') }}" class="btn btn-primary">Массовый бэкап</a>
    </div>
</div>

<section class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <a href="{{ route('servers.create') }}" class="btn btn-secondary !py-2">+ Сервер</a>
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
            <div class="text-xs font-mono text-slate-400 mt-1">{{ $server->ssh_user }}@{{ $server->host }}:{{ $server->ssh_port }}</div>
        </a>
    @empty
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="{{ route('servers.create') }}" class="text-brand-700 underline">Добавьте сервер</a>
            или прочитайте <a href="{{ route('guide') }}" class="underline">инструкцию</a>.
        </div>
    @endforelse
</section>

@if ($servers->contains(fn ($s) => $s->readyForBackup()))
<section class="card p-6">
    <h2 class="section-title">Очередь бэкапов</h2>
    <p class="text-sm text-slate-500 mb-4">
        Выберите несколько SSH-хостов, режим (файлы / базы / оба) и запустите.
        Серверы обрабатываются <strong>строго по очереди</strong>; статус проверяется по SSH раз в 15 минут.
    </p>
    <a href="{{ route('backup-batches.create') }}" class="btn btn-primary">Выбрать серверы и запустить</a>
</section>
@endif
@endsection
