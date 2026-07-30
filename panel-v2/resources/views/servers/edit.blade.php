@extends('layouts.app')

@section('title', 'Изменить — '.$server->name)

@section('content')
<div class="mb-8">
    <h1 class="page-title">Изменить сервер</h1>
</div>

<div class="help-box mb-6">
    <strong>Когда нужна переустановка restic:</strong>
    если меняете пароль шифрования, имя папки на Яндекс.Диске или токен —
    либо удалили папки бэкапа на Диске.
    После сохранения нажмите <strong>«Переустановить restic»</strong> на странице сервера.
</div>

<form method="post" action="{{ route('servers.update', $server) }}" class="card p-6 space-y-5">
    @csrf
    @method('PUT')
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
            <label class="label">Host</label>
            <input name="host" value="{{ old('host', $server->host) }}" required class="input">
        </div>
        <div>
            <label class="label">Порт</label>
            <input name="ssh_port" type="number" value="{{ old('ssh_port', $server->ssh_port) }}" required class="input">
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label">SSH пользователь</label>
            <input name="ssh_user" value="{{ old('ssh_user', $server->ssh_user) }}" required class="input">
        </div>
        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" placeholder="Не менять" class="input" autocomplete="off">
        </div>
    </div>
    <div>
        <label class="label">Пароль шифрования</label>
        <input name="restic_password" value="{{ old('restic_password', $server->restic_password) }}" required class="input">
        <p class="text-xs text-slate-400 mt-1">Смена пароля → обязательна переустановка restic (старые бэкапы со старым паролем не откроются).</p>
    </div>
    <div>
        <label class="label">Папка на Диске</label>
        <input name="restic_repo_slug" value="{{ old('restic_repo_slug', $server->restic_repo_slug) }}" class="input font-mono text-sm">
        <p class="text-xs text-slate-400 mt-1">Смена имени или удаление папок на Диске → переустановите restic.</p>
    </div>
    <div>
        <label class="label">Токен Яндекс.Диска (оставьте пустым, чтобы не менять)</label>
        <textarea name="rclone_token" rows="3" class="textarea font-mono !text-xs">{{ old('rclone_token') }}</textarea>
    </div>
    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="{{ route('servers.show', $server) }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<form method="post" action="{{ route('servers.destroy', $server) }}" class="mt-8 card p-5 border-red-100" onsubmit="return confirm('Удалить «{{ $server->name }}» из панели?\n\nБэкапы на Яндекс.Диске останутся.')">
    @csrf
    @method('DELETE')
    <h2 class="text-sm font-semibold text-red-700 mb-1">Удалить сервер</h2>
    <p class="text-sm text-slate-500 mb-4">Уберёт сервер из панели и связанные записи. Файлы на Яндекс.Диске не удаляются.</p>
    <button type="submit" class="btn btn-secondary !text-red-600 !border-red-200 hover:!bg-red-50">Удалить из панели</button>
</form>
@endsection
