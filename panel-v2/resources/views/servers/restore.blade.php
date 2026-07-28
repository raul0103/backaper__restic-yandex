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
    @php($path = $server->backupPaths->first())
    <div class="mb-4">
        <h3 class="font-semibold text-slate-900 mb-1">{{ $path?->displayName() ?? $server->fullBackupTarget()['label'] }}</h3>
        <pre class="code-block text-xs">restic snapshots --tag path:{{ $server->storageSlug($path?->displayName() ?? 'home') }}
# восстановить в /tmp/restore (потом скопируйте куда нужно):
restic restore latest --tag path:{{ $server->storageSlug($path?->displayName() ?? 'home') }} --target /tmp/restore</pre>
    </div>
</section>

<section class="card p-6">
    <h2 class="section-title">Базы данных</h2>
    <p class="text-sm text-slate-500 mb-3">Дампы лежат на Диске. Список и скачивание через rclone:</p>
    <pre class="code-block mb-4">rclone ls {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/
rclone copy {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/ИМЯ_БАЗЫ/ФАЙЛ.sql.gz ~/restore/
gunzip -c ~/restore/ФАЙЛ.sql.gz | mysql -u USER -p DATABASE</pre>
</section>
@endsection
