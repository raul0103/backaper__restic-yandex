@extends('layouts.app')

@section('title', 'Шаг 2 — '.$server->name)

@section('content')
@include('servers.wizard._steps', ['current' => 2, 'server' => $server])

<div class="mb-8">
    <h1 class="page-title">Шаг 2 — Поиск конфигов MODX</h1>
    <p class="page-subtitle">
        {{ $server->ssh_user.'@'.$server->host }}:{{ $server->ssh_port }}
        · <a href="{{ route('servers.wizard.step1', $server) }}" class="text-brand-600 hover:underline">SSH и restic</a>
    </p>
</div>

<section class="card p-5 mb-8 flex flex-wrap items-center justify-between gap-4">
    <p class="text-sm text-slate-600">Подключение: {{ $server->ssh_user }}@{{ $server->host }} (пароль SSH)</p>
    <form method="post" action="{{ route('servers.wizard.test-connection', $server) }}">
        @csrf
        <button type="submit" class="btn btn-secondary !text-sm">Проверить подключение</button>
    </form>
</section>

<section id="discovery-section"
         class="card p-6 sm:p-8"
         data-discover-url="{{ route('servers.wizard.discover-configs', $server) }}"
         data-status-url="{{ route('servers.wizard.discovery-status', $server) }}"
         data-cancel-url="{{ route('servers.wizard.cancel-discovery', $server) }}"
         data-csrf="{{ csrf_token() }}"
         data-initial-running="{{ $server->isDiscoveryRunning() ? '1' : '0' }}">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h2 class="section-title !mb-0">MODX config.inc.php</h2>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-violet" id="discover-btn">Найти конфиги на сервере</button>
            <button type="button" class="btn btn-secondary hidden" id="cancel-discover-btn">Прервать поиск</button>
        </div>
    </div>

    <p class="text-sm text-slate-500 mb-4">Сначала берём пути из <strong>php-fpm</strong> (<code class="text-xs">chdir</code>), nginx/apache (<code class="text-xs">root</code>) и <code class="text-xs">~/web/*/public_html</code>, затем ищем <code class="text-xs">*/core/config/*.inc.php</code>. Запасной вариант — pruned-поиск в <code class="text-xs">$HOME</code>.</p>

    <div id="discovery-status" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm"></div>

    <ul id="config-list" class="space-y-2 mb-6">
        @foreach ($server->modxConfigs as $config)
            <li class="card px-4 py-3">
                <div class="font-mono text-sm text-slate-800">{{ $config->config_path }}</div>
                @if($config->suggested_root_path)
                    <div class="text-xs text-slate-400 mt-1">→ {{ $config->suggested_root_path }}</div>
                @endif
            </li>
        @endforeach
    </ul>

    <div id="config-empty" class="{{ $server->modxConfigs->isNotEmpty() ? 'hidden' : '' }} rounded-xl border border-dashed border-slate-200 p-6 text-slate-500 text-sm mb-6 text-center">
        Конфиги ещё не найдены — нажмите «Найти конфиги на сервере».
    </div>

    <div id="proceed-wrap" class="{{ $server->modxConfigs->isEmpty() ? 'hidden' : '' }}">
        <form method="post" action="{{ route('servers.wizard.step3.go', $server) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Далее: базы данных →</button>
        </form>
    </div>
</section>

<script>
(function () {
    const initialStatus = @json($server->config_discovery_status);
    const initialError = @json($server->config_discovery_error);
    const initialFound = {{ $server->modxConfigs->count() }};

    const section = document.getElementById('discovery-section');
    if (!section) return;

    const discoverUrl = section.dataset.discoverUrl;
    const statusUrl = section.dataset.statusUrl;
    const cancelUrl = section.dataset.cancelUrl;
    const csrfToken = section.dataset.csrf;
    const statusEl = document.getElementById('discovery-status');
    const listEl = document.getElementById('config-list');
    const emptyEl = document.getElementById('config-empty');
    const proceedWrap = document.getElementById('proceed-wrap');
    const discoverBtn = document.getElementById('discover-btn');
    const cancelBtn = document.getElementById('cancel-discover-btn');
    let pollTimer = null;
    let cancelling = false;
    let starting = false;

    function setRunningUi(running) {
        discoverBtn.disabled = running || starting;
        discoverBtn.textContent = starting ? 'Запуск…' : (running ? 'Поиск…' : 'Найти конфиги на сервере');
        cancelBtn.classList.toggle('hidden', !running);
        cancelBtn.disabled = false;
        cancelBtn.textContent = 'Прервать поиск';
    }

    function showStatus(type, text) {
        statusEl.classList.remove('hidden');
        statusEl.className = 'mb-4 rounded-xl border px-4 py-3 text-sm ' + (
            type === 'running' ? 'bg-blue-50 border-blue-200 text-blue-800' :
            type === 'warn' ? 'bg-amber-50 border-amber-200 text-amber-900' :
            type === 'error' ? 'alert-error' :
            'alert-success'
        );
        statusEl.textContent = text;
    }

    function renderConfigs(configs) {
        listEl.innerHTML = '';
        configs.forEach(function (c) {
            const li = document.createElement('li');
            li.className = 'card px-4 py-3';
            li.innerHTML = '<div class="font-mono text-sm text-slate-800"></div><div class="text-xs text-slate-400 mt-1"></div>';
            li.querySelector('div:first-child').textContent = c.config_path;
            const sub = li.querySelector('div:last-child');
            if (c.suggested_root_path) {
                sub.textContent = '→ ' + c.suggested_root_path;
            } else {
                sub.remove();
            }
            listEl.appendChild(li);
        });

        const has = configs.length > 0;
        emptyEl.classList.toggle('hidden', has);
        proceedWrap.classList.toggle('hidden', !has);
    }

    function applyStatus(data) {
        renderConfigs(data.configs || []);

        if (data.running) {
            var msg = 'Идёт поиск на сервере… найдено: ' + (data.found || 0);
            if (data.remote_pid) {
                msg += ' · PID ' + data.remote_pid;
            }
            showStatus('running', msg);
            setRunningUi(true);
            return;
        }

        setRunningUi(false);
        starting = false;

        if (data.status === 'completed') {
            if ((data.found || 0) > 0) {
                showStatus('ok', 'Готово. Найдено конфигов: ' + data.found);
            } else {
                showStatus('warn', data.error || 'Поиск завершён — конфиги MODX не найдены');
            }
        } else if (data.status === 'failed') {
            showStatus('error', data.error || 'Поиск завершился с ошибкой');
        }
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok && !data.error) {
                    data.error = 'Ошибка сервера (HTTP ' + r.status + ')';
                }
                data._httpOk = r.ok;
                return data;
            }).catch(function () {
                throw new Error('Сервер вернул некорректный ответ (HTTP ' + r.status + ')');
            });
        });
    }

    function poll() {
        fetchJson(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (data) {
                applyStatus(data);

                if (!data.running) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                    cancelling = false;
                }
            })
            .catch(function (err) {
                showStatus('error', err.message || 'Не удалось получить статус поиска');
                setRunningUi(false);
                starting = false;
            });
    }

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, 3000);
    }

    function startDiscovery() {
        if (starting) return;
        starting = true;
        setRunningUi(true);
        showStatus('running', 'Подключаемся к серверу и запускаем поиск…');

        fetchJson(discoverUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        })
            .then(function (data) {
                starting = false;

                if (!data.ok) {
                    applyStatus(data);
                    setRunningUi(false);
                    showStatus('error', data.error || 'Не удалось запустить поиск');
                    return;
                }

                showStatus('running', data.message || 'Поиск запущен');
                startPolling();
            })
            .catch(function (err) {
                starting = false;
                setRunningUi(false);
                showStatus('error', err.message || 'Не удалось запустить поиск');
            });
    }

    discoverBtn.addEventListener('click', startDiscovery);

    cancelBtn.addEventListener('click', function () {
        if (cancelling) return;
        cancelling = true;
        cancelBtn.disabled = true;
        cancelBtn.textContent = 'Останавливаем…';

        fetchJson(cancelUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        })
            .then(function (data) {
                applyStatus(data);
                clearInterval(pollTimer);
                pollTimer = null;
                cancelling = false;
            })
            .catch(function (err) {
                cancelling = false;
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Прервать поиск';
                showStatus('error', err.message || 'Не удалось прервать поиск');
            });
    });

    if (section.dataset.initialRunning === '1') {
        startPolling();
    } else if (initialStatus === 'failed' && initialError) {
        showStatus('error', initialError);
    } else if (initialStatus === 'completed') {
        if (initialFound > 0) {
            showStatus('ok', 'Последний поиск завершён. Найдено конфигов: ' + initialFound);
        } else if (initialError) {
            showStatus('warn', initialError);
        }
    }
})();
</script>
@endsection
