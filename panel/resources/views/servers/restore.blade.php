@extends('layouts.app')

@section('title', 'Восстановление — '.$server->name)

@php
    $projects = $server->projects->where('is_enabled', true);
    $resticPassword = $server->restic_password ?: \App\Models\Server::DEFAULT_RESTIC_PASSWORD;
    $rcloneRemote = $server->rclone_remote ?: 'yandex';
@endphp

@section('content')
<div class="mb-8">
    <a href="{{ route('servers.show', $server) }}" class="inline-flex items-center gap-1 text-brand-600 font-medium text-sm hover:underline no-underline mb-4">
        ← К серверу {{ $server->name }}
    </a>
    <h1 class="page-title">Восстановление</h1>
    <p class="page-subtitle">{{ $server->ssh_user }}@{{ $server->host }} — команды выполняются на сервере по SSH</p>
</div>

{{-- Общая карточка --}}
<div class="card p-5 sm:p-6 mb-6">
    <h2 class="section-title !text-lg !mb-3">Ваши бэкапы</h2>
    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-slate-400 mb-1">Имя папки бэкапов</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $server->repoSlug() }}</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Пароль шифрования</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded">{{ $resticPassword }}</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Файлы сайтов</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $server->resticRepository() }}</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Базы данных</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/</dd>
        </div>
    </dl>
    <p class="text-sm text-slate-500 mt-4 mb-0">
        Сохраните имя папки, пароль и токен Яндекс.Диска — без них на новом сервере бэкапы не откроются.
        Файлы в restic видны только через команды ниже, не как обычные папки на Диске.
    </p>
</div>

{{-- Вкладки --}}
<div class="flex flex-wrap gap-2 mb-6" role="tablist">
    <button type="button" data-restore-tab="live"
            class="restore-tab btn btn-primary !text-sm">
        Сайт уже на этом сервере
    </button>
    <button type="button" data-restore-tab="new"
            class="restore-tab btn btn-secondary !text-sm">
        Сервер новый / старый умер
    </button>
</div>

{{-- ===== Вкладка: живой сервер ===== --}}
<div data-restore-panel="live" class="space-y-8">
    <p class="text-sm text-slate-600 -mt-2">
        Сервер живой, нужно откатить файлы или базу. Все команды — в одной SSH-сессии.
    </p>

    <section>
        <h2 class="section-title">1. Подключиться и открыть доступ к облаку</h2>
        <pre class="code-block">ssh {{ $server->ssh_user }}@{{ $server->host }} -p {{ $server->ssh_port }}

source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"</pre>
        <p class="text-xs text-slate-500 mt-2 mb-0">Эти две строки с <code>source</code> нужны перед каждыми командами ниже.</p>
    </section>

    <section>
        <h2 class="section-title">2. Вернуть базу данных (если нужно)</h2>
        <p class="text-sm text-slate-600 mb-3">Сначала посмотрите список дампов, потом скачайте нужный и залейте в MySQL.</p>
        <pre class="code-block mb-4">rclone ls {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/</pre>

        @forelse($projects as $project)
            @if($project->database)
                <div class="card p-4 mb-3">
                    <h3 class="font-semibold text-slate-900 mb-1">{{ $project->name }}</h3>
                    <p class="text-xs text-slate-500 font-mono mb-3">
                        {{ $project->database->database_name }} · {{ $project->database->database_user }}
                    </p>
                    <pre class="code-block text-xs">rclone copy \
  {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/{{ $project->database->database_name }}/ИМЯ_ФАЙЛА.sql.gz \
  ~/restore/

gunzip -c ~/restore/ИМЯ_ФАЙЛА.sql.gz | \
  mariadb -h {{ $project->database->database_server }} \
  -u {{ $project->database->database_user }} \
  --password='ВАШ_ПАРОЛЬ_БД' \
  {{ $project->database->database_name }}</pre>
                </div>
            @endif
        @empty
            <p class="text-sm text-slate-500">Проекты в панели не настроены — подставьте имя БД из списка дампов вручную.</p>
        @endforelse
    </section>

    <section>
        <h2 class="section-title">3. Вернуть файлы сайта</h2>
        <p class="text-sm text-slate-600 mb-2">Посмотрите снимки (точки восстановления):</p>
        <pre class="code-block mb-4">restic snapshots</pre>

        @forelse($projects as $project)
            <div class="card p-4 mb-4">
                <h3 class="font-semibold text-slate-900 mb-1">{{ $project->name }}</h3>
                <p class="text-xs font-mono text-slate-500 mb-3">{{ $project->root_path }}</p>

                <p class="text-sm text-slate-600 mb-1">Снимки только этого проекта:</p>
                <pre class="code-block text-xs mb-3">restic snapshots --tag project:{{ $server->storageSlug($project->name) }}</pre>

                <p class="text-sm text-slate-600 mb-1">Весь сайт <strong>на то же место</strong> (перезапишет файлы):</p>
                <pre class="code-block text-xs mb-3">SNAPSHOT=&lt;id-из-списка&gt;

restic restore "$SNAPSHOT" --target / \
  --include "{{ $project->root_path }}/**"</pre>

                <details class="mt-2">
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Нужна другая папка или один файл</summary>
                    <div class="mt-3 space-y-3">
                        <p class="text-sm text-slate-600 mb-0">Другая папка (временные файлы удалятся сами):</p>
                        <pre class="code-block text-xs">SNAPSHOT=&lt;id-из-списка&gt;
OLD="{{ $project->root_path }}"
NEW="/home/USER/web/DOMAIN/public_html"

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
restic restore "$SNAPSHOT" --target "$TMP" --include "$OLD/**"
mkdir -p "$NEW"
cp -a "$TMP$OLD/." "$NEW/"</pre>
                        <p class="text-sm text-slate-600 mb-0">Один файл сразу на место:</p>
                        <pre class="code-block text-xs">restic restore &lt;id&gt; --target / \
  --include "{{ $project->root_path }}/.htpasswd"</pre>
                    </div>
                </details>
            </div>
        @empty
            <div class="card p-4 mb-4">
                <p class="text-sm text-slate-600 mb-2">Подставьте путь к сайту из снимка:</p>
                <pre class="code-block text-xs">restic restore &lt;id&gt; --target / \
  --include "/home/user/web/DOMAIN/public_html/**"</pre>
            </div>
        @endforelse
    </section>

    <section>
        <h2 class="section-title">4. Проверить сайт</h2>
        <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
            <li>В <code>core/config/config.inc.php</code> — логин/пароль БД и URL сайта.</li>
            <li>Удалите содержимое <code>core/cache/</code>.</li>
            <li>Откройте сайт и менеджер MODX.</li>
        </ul>
    </section>
</div>

{{-- ===== Вкладка: новый сервер ===== --}}
<div data-restore-panel="new" class="space-y-8 hidden">
    <p class="text-sm text-slate-600 -mt-2">
        Старый хостинг умер. Бэкапы уже в Яндекс.Диске — поднимаете новый сервер и накатываете сайт.
    </p>

    <section>
        <h2 class="section-title">1. Что взять с собой</h2>
        <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
            <li>Имя папки бэкапов: <code class="font-mono">{{ $server->repoSlug() }}</code></li>
            <li>Пароль: <code class="font-mono">{{ $resticPassword }}</code></li>
            <li>Токен Яндекс.Диска (JSON) — с шага 1 мастера</li>
            <li>Пути проектов и имена БД с этой страницы</li>
        </ul>
    </section>

    <section>
        <h2 class="section-title">2. Подготовить новый хостинг</h2>
        <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside">
            <li>Создайте сайт (домен, <code>public_html</code>, PHP).</li>
            <li>Создайте пустую MySQL-базу и пользователя.</li>
            <li>Запомните новый путь, например <code class="font-mono text-xs">/home/USER/web/DOMAIN/public_html</code>.</li>
            <li>Нужен SSH (лучше пользователь сайта, не обязательно root).</li>
        </ol>
    </section>

    <section>
        <h2 class="section-title">3. Подключить бэкапы через панель</h2>
        <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside mb-3">
            <li>Добавьте <strong>новый сервер</strong> в этой панели (SSH нового хоста).</li>
            <li>На шаге 1 укажите <strong>то же имя папки</strong>
                (<code class="font-mono">{{ $server->repoSlug() }}</code>
                и <strong>тот же пароль</strong> — иначе старые снимки не откроются.</li>
            <li>Вставьте токен Яндекс.Диска → Сохранить → <strong>«Установить restic»</strong>.</li>
            <li>Проверка на сервере:</li>
        </ol>
        <pre class="code-block text-xs mb-3">source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"
restic snapshots</pre>
        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-0">
            Не запускайте <code>restic init</code> вручную — репозиторий уже есть в облаке.
        </p>

        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <summary class="cursor-pointer font-medium text-slate-900">Если без панели — ручная установка</summary>
            <pre class="code-block text-xs mt-3 mb-0">mkdir -p ~/bin ~/backaper && export PATH="$HOME/bin:$PATH"

curl -fsSL https://github.com/restic/restic/releases/download/v0.19.1/restic_0.19.1_linux_amd64.bz2 \
  | bunzip2 > ~/bin/restic && chmod +x ~/bin/restic
curl -fsSL https://downloads.rclone.org/rclone-current-linux-amd64.zip -o /tmp/rclone.zip
unzip -qo /tmp/rclone.zip -d /tmp && install -m 755 /tmp/rclone-*/rclone ~/bin/rclone

# токен JSON в ~/backaper/rclone-token.json, затем:
cat > ~/backaper/rclone.conf <<EOF
[{{ $rcloneRemote }}]
type = yandex
token = $(tr -d '\n' < ~/backaper/rclone-token.json)
EOF

cat > ~/backaper/backaper.env <<EOF
export RESTIC_REPOSITORY='{{ $server->resticRepository() }}'
export RESTIC_PASSWORD='{{ $resticPassword }}'
export BACKAPER_RCLONE_REMOTE='{{ $rcloneRemote }}'
export BACKAPER_CLOUD_PREFIX='{{ $server->cloudPrefix() }}'
export RCLONE_CONFIG="\$HOME/backaper/rclone.conf"
export PATH="\$HOME/bin:\$PATH"
EOF
chmod 600 ~/backaper/backaper.env ~/backaper/rclone.conf
source ~/backaper/backaper.env
restic snapshots</pre>
        </details>
    </section>

    <section>
        <h2 class="section-title">4. Накатить файлы</h2>
        <p class="text-sm text-slate-600 mb-3">
            В снимке лежит <strong>старый</strong> путь. На новом хосте путь часто другой — используйте блок ниже.
        </p>
        @forelse($projects as $project)
            <div class="card p-4 mb-3">
                <h3 class="font-semibold text-slate-900 mb-1">{{ $project->name }}</h3>
                <p class="text-xs font-mono text-slate-500 mb-3">Было: {{ $project->root_path }}</p>
                <pre class="code-block text-xs">source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"
restic snapshots --tag project:{{ $server->storageSlug($project->name) }}
SNAPSHOT=&lt;id-из-списка&gt;

OLD="{{ $project->root_path }}"
NEW="/home/USER/web/DOMAIN/public_html"   # ← новый путь

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
restic restore "$SNAPSHOT" --target "$TMP" --include "$OLD/**"
mkdir -p "$NEW"
cp -a "$TMP$OLD/." "$NEW/"</pre>
            </div>
        @empty
            <pre class="code-block text-xs">SNAPSHOT=&lt;id&gt;
OLD="/home/olduser/web/old-domain/public_html"
NEW="/home/USER/web/DOMAIN/public_html"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
restic restore "$SNAPSHOT" --target "$TMP" --include "$OLD/**"
mkdir -p "$NEW"
cp -a "$TMP$OLD/." "$NEW/"</pre>
        @endforelse
    </section>

    <section>
        <h2 class="section-title">5. Накатить базу</h2>
        <pre class="code-block text-xs mb-3">source ~/backaper/backaper.env
rclone ls {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/
mkdir -p ~/restore
rclone copy {{ $rcloneRemote }}:{{ $server->cloudPrefix() }}/databases/ИМЯ_БД/ИМЯ_ФАЙЛА.sql.gz ~/restore/

gunzip -c ~/restore/ИМЯ_ФАЙЛА.sql.gz | \
  mariadb -h localhost -u DB_USER --password='DB_PASS' DB_NAME</pre>
        @foreach($projects as $project)
            @if($project->database)
                <p class="text-xs text-slate-500 mb-1">
                    {{ $project->name }}: была БД
                    <code class="font-mono">{{ $project->database->database_name }}</code>
                    (на новом сервере имя может быть другим).
                </p>
            @endif
        @endforeach
    </section>

    <section>
        <h2 class="section-title">6. Починить MODX</h2>
        <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside">
            <li>В <code>core/config/config.inc.php</code> — новые хост БД, имя, логин, пароль, пути и URL.</li>
            <li>Права: <code>chown -R USER:USER /path/to/public_html</code>.</li>
            <li>Очистите <code>core/cache/</code>.</li>
            <li>Откройте сайт. Дальше в панели можно снова настроить бэкапы с тем же именем папки
                <code class="font-mono">{{ $server->repoSlug() }}</code>.</li>
        </ol>
    </section>
</div>

<script>
(function () {
    const tabs = document.querySelectorAll('[data-restore-tab]');
    const panels = document.querySelectorAll('[data-restore-panel]');
    function show(name) {
        tabs.forEach(function (btn) {
            const active = btn.getAttribute('data-restore-tab') === name;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-secondary', !active);
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-restore-panel') !== name);
        });
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    }
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () { show(btn.getAttribute('data-restore-tab')); });
    });
    const hash = (location.hash || '').replace('#', '');
    show(hash === 'new' ? 'new' : 'live');
})();
</script>
@endsection
