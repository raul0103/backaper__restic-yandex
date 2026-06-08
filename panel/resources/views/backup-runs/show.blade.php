@extends('layouts.app')

@section('title', 'Бэкап #'.$run->id)

@section('content')
@php($parsed = $run->parsedLog())

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

<div id="run-sizes-wrap">
    @include('backup-runs._sizes', ['run' => $run, 'parsed' => $parsed])
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
    const sizesWrap = document.getElementById('run-sizes-wrap');
    const POLL_MS = 3000;
    let pollTimer = null;
    let inFlight = false;
    let stopped = false;

    function schedulePoll(delay) {
        if (stopped) return;
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, delay);
    }

    function stopPolling() {
        stopped = true;
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    function badgeClass(status) {
        if (status === 'completed') return 'badge badge-success';
        if (status === 'running') return 'badge bg-blue-100 text-blue-800';
        return 'badge badge-error';
    }

    function renderSizes(parsed) {
        if (!parsed || !sizesWrap) return;

        const cloud = parsed.cloud || {};
        const artifacts = parsed.artifacts || [];
        const restic = parsed.restic;
        const hasCloud = cloud.total || cloud.free || cloud.used;
        const hasSizes = hasCloud || artifacts.length || restic;

        if (!hasSizes && !parsed.insufficient_storage) {
            sizesWrap.innerHTML = '';
            return;
        }

        let html = '';

        if (parsed.insufficient_storage) {
            html += '<div class="mb-4 rounded-xl border px-4 py-3 text-sm alert-error">';
            html += '<strong>Недостаточно места на Яндекс.Диске</strong> (ошибка 507). ';
            html += 'Освободите место в облаке — это не RAM на сервере.';
            if (cloud.free) {
                html += ' Свободно: ' + cloud.free + (cloud.total ? ' из ' + cloud.total : '') + '.';
            }
            html += '</div>';
        }

        if (hasSizes) {
            html += '<div id="run-sizes" class="card p-4 sm:p-6 mb-4">';
            html += '<h2 class="section-title !text-base !mb-3">Размеры и облако</h2>';

            if (hasCloud) {
                html += '<dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 text-sm">';
                [['total', 'Всего на Диске'], ['used', 'Занято'], ['free', 'Свободно'], ['trashed', 'В корзине']].forEach(function (pair) {
                    if (cloud[pair[0]]) {
                        html += '<div><dt class="text-slate-400">' + pair[1] + '</dt><dd class="font-medium">' + cloud[pair[0]] + '</dd></div>';
                    }
                });
                html += '</dl>';
            }

            if (artifacts.length || restic) {
                html += '<div class="table-wrap"><table class="data-table text-sm"><thead><tr><th>Артефакт</th><th>Размер</th><th>В облаке</th></tr></thead><tbody>';
                artifacts.forEach(function (item) {
                    html += '<tr><td>' + (item.type === 'db' ? 'Дамп БД' : 'Tar проекта') + ' <code class="text-xs">' + item.name + '</code></td>';
                    html += '<td class="font-mono">' + item.human + '</td><td>' + (item.uploaded ? '<span class="badge badge-success">да</span>' : '<span class="badge badge-error">нет</span>') + '</td></tr>';
                });
                if (restic) {
                    html += '<tr><td>Restic (файлы сайта)</td><td class="font-mono">' + (restic.stored || restic.added || '—') + '</td><td>';
                    if (restic.files) html += '<span class="text-slate-500 text-xs">' + restic.files.toLocaleString() + ' файлов</span>';
                    html += '</td></tr>';
                }
                html += '</tbody></table></div>';
            }

            html += '</div>';
        }

        sizesWrap.innerHTML = html;
    }

    function applyStatus(data) {
        if (data.log) {
            logEl.textContent = data.log;
        }

        if (data.sizes) {
            renderSizes(data.sizes);
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
        if (inFlight || stopped) return;
        inFlight = true;

        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                applyStatus(data);
                if (!data.running) {
                    stopPolling();
                }
            })
            .catch(function () {})
            .finally(function () {
                inFlight = false;
                if (!stopped) {
                    schedulePoll(POLL_MS);
                }
            });
    }

    schedulePoll(POLL_MS);
})();
</script>
@endif
@endsection
