@extends('layouts.app')

@section('title', 'Шаг 1 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 1, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 1 — SSH и облако</h1>
    <p class="page-subtitle">{{ $server->ssh_user.'@'.$server->host }}:{{ $server->ssh_port }}</p>
</div>

<div class="card p-6 sm:p-8 max-w-2xl">
    <form method="post" action="{{ route('servers.wizard.step1.update', $server) }}" class="space-y-5">
        @csrf

        <h2 class="section-title !mb-4">SSH</h2>

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

        <h2 class="section-title !mb-4 pt-2">Restic / Rclone</h2>

        @include('servers.wizard._step1_fields')

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            @if($server->isWizardComplete())
                <a href="{{ route('servers.show', $server) }}" class="btn btn-secondary">← К серверу</a>
            @elseif($server->setup_step >= \App\Models\Server::STEP_SETTINGS)
                <a href="{{ route('servers.wizard.step2', $server) }}" class="btn btn-secondary">К шагу 2 →</a>
            @endif
        </div>
    </form>
</div>
@endsection
