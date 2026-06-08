@extends('layouts.app')

@section('title', 'Новый сервер')

@section('content')
@include('servers.wizard._steps', ['current' => 1])

<div class="mb-8">
    <h1 class="page-title">Шаг 1 — SSH и облако</h1>
    <p class="page-subtitle">Подключение к серверу и настройки restic / rclone для бэкапов.</p>
</div>

<div class="card p-6 sm:p-8 max-w-2xl">
    <form method="post" action="{{ route('servers.store') }}" class="space-y-5">
        @csrf

        <h2 class="section-title !mb-4">SSH</h2>

        <div>
            <label class="label">Название</label>
            <input name="name" value="{{ old('name') }}" required placeholder="prod-vps-1" class="input @error('name') input-error @enderror">
            @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Host</label>
                <input name="host" value="{{ old('host') }}" required placeholder="192.168.1.10" class="input">
            </div>
            <div>
                <label class="label">SSH порт</label>
                <input name="ssh_port" type="number" value="{{ old('ssh_port', 22) }}" required class="input">
            </div>
        </div>

        <div>
            <label class="label">SSH пользователь</label>
            <input name="ssh_user" value="{{ old('ssh_user') }}" required placeholder="deploy" class="input">
        </div>

        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" value="{{ old('ssh_password') }}" required class="input" autocomplete="off">
            @error('ssh_password')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <h2 class="section-title !mb-4 pt-2">Restic / Rclone</h2>

        @php($server = new \App\Models\Server(['rclone_remote' => 'yandex']))
        @include('servers.wizard._step1_fields')

        <button type="submit" class="btn btn-primary w-full sm:w-auto">Создать и перейти к шагу 2</button>
    </form>
</div>
@endsection
