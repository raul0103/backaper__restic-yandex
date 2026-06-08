@extends('layouts.app')

@section('title', 'Бэкап #'.$run->id)

@section('content')
<div class="mb-8">
    <div class="flex flex-wrap items-center gap-3 mb-2">
        <h1 class="page-title !text-2xl">Бэкап #{{ $run->id }}</h1>
        @if($run->status === 'completed')
            <span id="run-badge" class="badge badge-success">{{ $run->status }}</span>
        @elseif($run->status === 'running')
            <span id="run-badge" class="badge bg-blue-100 text-blue-800">{{ $run->status }}</span>
        @else
            <span id="run-badge" class="badge badge-error">{{ $run->status }}</span>
        @endif
    </div>
    <p class="page-subtitle">
        {{ $run->server->name }}
        @if($run->project) / {{ $run->project->name }} @endif
        · {{ $run->created_at?->format('d.m.Y H:i:s') }}
    </p>
</div>

<div id="run-status" class="@if($run->isRunning()) mb-4 rounded-xl border px-4 py-3 text-sm bg-blue-50 border-blue-200 text-blue-800 @else hidden @endif">
    @if($run->isRunning())
        Идёт бэкап на сервере…
        @if($run->remote_pid)
            PID {{ $run->remote_pid }}
        @endif
    @endif
</div>

<pre id="run-log" class="log-block min-h-[240px]">{{ $run->log ?: ($run->isRunning() ? 'Ожидание лога с сервера…' : 'Лог пуст') }}</pre>

<a href="{{ route('servers.show', $run->server_id) }}" class="inline-flex items-center gap-1 mt-6 text-brand-600 font-medium text-sm hover:underline no-underline">
    ← К серверу
</a>

@if($run->isRunning())
<script>
(function () {
    const statusUrl = @json(route('backup-runs.status', $run));
    const logEl = document.getElementById('run-log');
    const statusEl = document.getElementById('run-status');
    const badgeEl = document.getElementById('run-badge');
    let pollTimer = null;

    function badgeClass(status) {
        if (status === 'completed') return 'badge badge-success';
        if (status === 'running') return 'badge bg-blue-100 text-blue-800';
        return 'badge badge-error';
    }

    function applyStatus(data) {
        if (data.log) {
            logEl.textContent = data.log;
        }

        badgeEl.textContent = data.status;
        badgeEl.className = badgeClass(data.status);

        if (data.running) {
            statusEl.classList.remove('hidden');
            statusEl.className = 'mb-4 rounded-xl border px-4 py-3 text-sm bg-blue-50 border-blue-200 text-blue-800';
            statusEl.textContent = 'Идёт бэкап на сервере…'
                + (data.remote_pid ? ' · PID ' + data.remote_pid : '');
            return;
        }

        statusEl.classList.add('hidden');

        if (data.status === 'completed') {
            statusEl.classList.remove('hidden');
            statusEl.className = 'mb-4 rounded-xl border px-4 py-3 text-sm alert-success';
            statusEl.textContent = 'Бэкап завершён';
        } else if (data.status === 'failed') {
            statusEl.classList.remove('hidden');
            statusEl.className = 'mb-4 rounded-xl border px-4 py-3 text-sm alert-error';
            statusEl.textContent = 'Бэкап завершился с ошибкой';
        }
    }

    function poll() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                applyStatus(data);
                if (!data.running) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            })
            .catch(function () {
                // keep polling on transient errors
            });
    }

    pollTimer = setInterval(poll, 3000);
    poll();
})();
</script>
@endif
@endsection
