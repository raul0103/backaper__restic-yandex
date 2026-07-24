@extends('layouts.app')

@section('title', 'Восстановление — '.$server->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('servers.show', $server) }}" class="text-sm text-brand-700 font-medium no-underline hover:underline">← К серверу {{ $server->name }}</a>
</div>

<div class="mb-8">
    <h1 class="page-title">Восстановление</h1>
    <p class="page-subtitle">Файлы — через restic. Базы — скачать .sql.gz и импортировать.</p>
</div>

<div class="help-box mb-6">
    На сервере сначала выполните: <code class="text-xs bg-white px-1 rounded">source ~/backaper/backaper.env</code>
</div>

@php($rcloneRemote = $server->rclone_remote ?: 'yandex')

<section class="card p-6 mb-6">
    <h2 class="section-title">Файлы (restic)</h2>
    <pre class="code-block mb-3">source ~/backaper/backaper.env
restic snapshots</pre>
    @foreach ($server->backupPaths->where('is_enabled', true) as $path)
        <div class="mb-4">
            <h3 class="font-semibold text-slate-900 mb-1">{{ $path->displayName() }}</h3>
            <pre class="code-block text-xs">restic snapshots --tag path:{{ $server->storageSlug($path->displayName()) }}
# восстановить в /tmp/restore (потом скопируйте куда нужно):
restic restore latest --tag path:{{ $server->storageSlug($path->displayName()) }} --target /tmp/restore</pre>
        </div>
    @endforeach
</section>

<section class="card p-6">
    <h2 class="section-title">Базы данных</h2>
    <pre class="code-block mb-4">rclone ls {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/</pre>
    @forelse ($server->databases->where('is_enabled', true) as $db)
        <div class="mb-4">
            <h3 class="font-semibold text-slate-900 mb-1">{{ $db->displayName() }}</h3>
            <pre class="code-block text-xs">rclone copy {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/{{ $server->storageSlug($db->database_name) }}/ИМЯ_ФАЙЛА.sql.gz ~/restore/
gunzip -c ~/restore/ИМЯ_ФАЙЛА.sql.gz | mysql -u {{ $db->database_user }} -p {{ $db->database_name }}</pre>
        </div>
    @empty
        <p class="text-sm text-slate-400">Нет включённых баз</p>
    @endforelse
</section>
@endsection
