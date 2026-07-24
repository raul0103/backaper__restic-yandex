@extends('layouts.app')

@section('title', 'Шаг 1 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 1, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 1 — Подключение</h1>
    <p class="page-subtitle">Доступ по SSH и куда складывать бэкапы.</p>
</div>

<form method="post" action="{{ route('servers.wizard.connect.update', $server) }}" class="card p-6 space-y-5">
    @csrf
    <div>
        <label class="label">Название</label>
        <input name="name" value="{{ old('name', $server->name) }}" required class="input">
    </div>
    <div>
        <label class="label">Тип</label>
        <select name="kind" class="input">
            <option value="hosting" @selected(old('kind', $server->kind) === 'hosting')>Хостинг</option>
            <option value="vps" @selected(old('kind', $server->kind) === 'vps')>VPS</option>
        </select>
    </div>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
            <label class="label">SSH хост</label>
            <input name="host" value="{{ old('host', $server->host) }}" required class="input">
        </div>
        <div>
            <label class="label">Порт</label>
            <input name="ssh_port" type="number" value="{{ old('ssh_port', $server->ssh_port) }}" required class="input">
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label">SSH логин</label>
            <input name="ssh_user" value="{{ old('ssh_user', $server->ssh_user) }}" required class="input">
        </div>
        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" placeholder="Оставьте пустым, чтобы не менять" class="input" autocomplete="off">
        </div>
    </div>
    <div>
        <label class="label">Пароль шифрования</label>
        <input name="restic_password" value="{{ old('restic_password', $server->restic_password) }}" required class="input font-mono text-sm">
    </div>
    <div>
        <label class="label">Папка на Яндекс.Диске</label>
        <input name="restic_repo_slug" value="{{ old('restic_repo_slug', $server->restic_repo_slug) }}" class="input font-mono text-sm">
    </div>
    <div>
        <label class="label">Токен Яндекс.Диска</label>
        <textarea name="rclone_token" rows="3" required class="textarea font-mono !text-xs">{{ old('rclone_token', $server->rclone_token) }}</textarea>
    </div>
    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn btn-primary">Сохранить →</button>
        <button type="submit" formaction="{{ route('servers.wizard.test-connection', $server) }}" class="btn btn-secondary">Проверить SSH</button>
    </div>
</form>
@endsection
