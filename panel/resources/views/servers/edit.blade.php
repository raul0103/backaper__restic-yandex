@extends('layouts.app')

@section('title', 'Настройки '.$server->name)

@section('content')
<div class="mb-8">
    <h1 class="page-title">Настройки</h1>
    <p class="page-subtitle">{{ $server->name }}</p>
</div>

<div class="card p-6 sm:p-8 max-w-2xl">
    <form method="post" action="{{ route('servers.update', $server) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="label">Название</label>
            <input name="name" value="{{ old('name', $server->name) }}" required class="input">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Host</label>
                <input name="host" value="{{ old('host', $server->host) }}" required class="input">
            </div>
            <div>
                <label class="label">SSH порт</label>
                <input name="ssh_port" type="number" value="{{ old('ssh_port', $server->ssh_port) }}" required class="input">
            </div>
        </div>

        <div>
            <label class="label">SSH пользователь</label>
            <input name="ssh_user" value="{{ old('ssh_user', $server->ssh_user) }}" required class="input">
        </div>

        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" placeholder="Оставьте пустым, чтобы не менять" class="input" autocomplete="off">
        </div>

        <div>
            <label class="label">RESTIC_PASSWORD</label>
            <input name="restic_password" type="password" value="{{ old('restic_password', $server->restic_password) }}" required class="input">
        </div>

        <div>
            <label class="label">Rclone remote</label>
            <input name="rclone_remote" value="{{ old('rclone_remote', $server->rclone_remote ?? 'yandex') }}" class="input">
        </div>

        <div>
            <label class="label">Rclone OAuth token (JSON)</label>
            <textarea name="rclone_token" rows="4" class="textarea font-mono !text-xs" placeholder='{"access_token":"...","token_type":"bearer",...}'>{{ old('rclone_token', $server->rclone_token) }}</textarea>
        </div>

        @include('servers._rclone_help')

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('servers.show', $server) }}" class="btn btn-secondary">Назад</a>
        </div>
    </form>
</div>
@endsection
