@extends('layouts.app')

@section('title', 'Восстановление — '.$server->name)

@section('content')
<div class="mb-8">
    <a href="{{ route('servers.show', $server) }}" class="inline-flex items-center gap-1 text-brand-600 font-medium text-sm hover:underline no-underline mb-4">
        ← К серверу {{ $server->name }}
    </a>
    <h1 class="page-title">Восстановление бэкапа</h1>
    <p class="page-subtitle">
        {{ $server->ssh_user }}@{{ $server->host }} — команды на сервере по SSH.
        Если сервер умер — см. <a href="#disaster" class="text-brand-600 hover:underline">раздел 5</a>.
    </p>
</div>

<div class="card p-5 sm:p-6 mb-6">
    <h2 class="section-title !text-lg">Где лежат бэкапы</h2>
    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-slate-400 mb-1">Файлы (restic snapshots)</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $server->resticRepository() }}</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Базы данных (.sql.gz)</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $server->rclone_remote }}:{{ $server->cloudPrefix() }}/databases/</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Slug репозитория (не менять при переносе)</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded break-all">{{ $server->repoSlug() }}</dd>
        </div>
        <div>
            <dt class="text-slate-400 mb-1">Пароль шифрования restic</dt>
            <dd class="font-mono text-xs bg-slate-50 p-2 rounded">{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}</dd>
        </div>
    </dl>
    <p class="text-sm text-slate-500 mt-4 mb-0">
        Файлы в restic не видны как обычные папки на Яндекс.Диске — только через <code>restic</code>.
        Дампы БД — обычные файлы (rclone или веб-интерфейс Диска).
        Сохраните slug, пароль и OAuth-токен rclone — без них новый сервер к бэкапам не подключится.
    </p>
</div>

<section class="mb-10">
    <h2 class="section-title">1. Подключиться к серверу</h2>
    <pre class="code-block">ssh {{ $server->ssh_user }}@{{ $server->host }} -p {{ $server->ssh_port }}</pre>
    <p class="text-sm text-slate-500 mt-2">Загрузить переменные restic/rclone (нужны для всех команд ниже):</p>
    <pre class="code-block">source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"</pre>
    <p class="text-sm text-slate-500 mt-2 mb-0">
        Пароль шифрования restic для всех проектов:
        <code class="font-mono">{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}</code>
        (уже в <code>backaper.env</code> как <code>RESTIC_PASSWORD</code>).
    </p>
</section>

<section class="mb-10">
    <h2 class="section-title">2. База данных — вручную</h2>
    <p class="text-sm text-slate-600 mb-4">
        Список дампов на Яндекс.Диске, скачивание на сервер и импорт в MySQL/MariaDB.
    </p>

    <h3 class="font-medium text-slate-800 mb-2">Список дампов</h3>
    <pre class="code-block">rclone ls {{ $server->rclone_remote }}:{{ $server->cloudPrefix() }}/databases/</pre>

    @foreach($server->projects->where('is_enabled', true) as $project)
        @if($project->database)
            <div class="card p-4 mb-4">
                <h4 class="font-semibold text-slate-900 mb-2">{{ $project->name }}</h4>
                <p class="text-xs text-slate-500 mb-3 font-mono">{{ $project->database->database_name }} · пользователь {{ $project->database->database_user }}</p>

                <p class="text-sm text-slate-600 mb-1">Скачать последний дамп (подставьте имя файла из списка выше):</p>
                <pre class="code-block text-xs">rclone copy \
  {{ $server->rclone_remote }}:{{ $server->cloudPrefix() }}/databases/{{ $project->database->database_name }}/ИМЯ_ФАЙЛА.sql.gz \
  ~/restore/</pre>

                <p class="text-sm text-slate-600 mb-1 mt-3">Восстановить в БД:</p>
                <pre class="code-block text-xs">gunzip -c ~/restore/ИМЯ_ФАЙЛА.sql.gz | \
  mariadb -h {{ $project->database->database_server }} -u {{ $project->database->database_user }} -p {{ $project->database->database_name }}</pre>
                <p class="text-xs text-slate-400 mt-2 mb-0">Пароль — из <code>core/config/config.inc.php</code> или из панели (шаг 3 мастера). На MariaDB 11 используйте <code>--password=...</code> вместо интерактивного <code>-p</code>.</p>
            </div>
        @endif
    @endforeach
</section>

<section class="mb-10">
    <h2 class="section-title">3. Файлы проекта — через restic</h2>
    <p class="text-sm text-slate-600 mb-4">
        Сначала выполните шаг 1 (<code>source ~/backaper/backaper.env</code>) в этой же SSH-сессии.
    </p>

    <h3 class="font-medium text-slate-800 mb-2">Список снимков</h3>
    <pre class="code-block">restic snapshots</pre>

    <h3 class="font-medium text-slate-800 mb-2 mt-6">Содержимое снимка</h3>
    <pre class="code-block">restic ls &lt;snapshot-id&gt;</pre>
    <p class="text-sm text-slate-500 mb-4">Или только папку проекта:</p>
    @foreach($server->projects->where('is_enabled', true) as $project)
        <pre class="code-block text-xs mb-2">restic ls &lt;snapshot-id&gt; {{ $project->root_path }}</pre>
    @endforeach

    <div class="card p-4 mb-6 border-amber-200 bg-amber-50/50">
        <h3 class="font-medium text-slate-900 mb-2">Как работает <code>--target</code></h3>
        <p class="text-sm text-slate-700 mb-2">
            <code>--target</code> — это <strong>корень</strong>, под которым restic заново создаёт <strong>полный путь из снимка</strong>.
            Это не «положи файл прямо в эту папку».
        </p>
        <ul class="text-sm text-slate-700 space-y-1 list-disc list-inside mb-3">
            <li><code>--target /</code> — сразу в исходные пути на сервере (как в снимке)</li>
            <li><code>--target ~/restore-partial</code> — во временную папку; потом копируете куда нужно</li>
        </ul>
        <p class="text-xs text-slate-500 mb-0">
            Пример ошибки: <code>--target …/public_html</code> + файл <code>…/public_html/.htpasswd</code>
            → получится <code>…/public_html/home/user/…/public_html/.htpasswd</code>.
        </p>
    </div>

    <h3 class="font-medium text-slate-800 mb-2">Сразу в нужную папку (исходный путь)</h3>
    <p class="text-sm text-slate-600 mb-3">
        Если путь в снимке совпадает с тем, куда восстанавливаете — используйте <code>--target /</code>:
    </p>
    @php($exampleRoot = $server->projects->where('is_enabled', true)->first()?->root_path ?? '/home/user/web/example/public_html')
    <pre class="code-block text-xs"># один файл сразу на место
restic restore &lt;snapshot-id&gt; --target / \
  --include "{{ $exampleRoot }}/.htpasswd"

# несколько файлов / папка
restic restore &lt;snapshot-id&gt; --target / \
  --include "{{ $exampleRoot }}/core/config/config.inc.php" \
  --include "{{ $exampleRoot }}/.htaccess"</pre>
    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3 mb-6">
        Файлы перезапишутся без промежуточной копии. Перед этим сделайте бэкап текущих файлов, если они важны.
    </p>

    <h3 class="font-medium text-slate-800 mb-2">Через временную папку (безопаснее)</h3>
    <p class="text-sm text-slate-600 mb-3">
        Сначала в <code>~/restore-partial</code>, проверяете, потом копируете куда нужно:
    </p>
    <pre class="code-block text-xs">restic restore &lt;snapshot-id&gt; --target ~/restore-partial \
  --include "{{ $exampleRoot }}/.htpasswd"

# файл окажется здесь:
# ~/restore-partial{{ $exampleRoot }}/.htpasswd

cp ~/restore-partial{{ $exampleRoot }}/.htpasswd \
   {{ $exampleRoot }}/.htpasswd</pre>
    <p class="text-sm text-slate-500 mt-2 mb-6">Флаг <code>--include</code> можно повторять. Путь — абсолютный, как в <code>restic ls</code>.</p>

    @foreach($server->projects->where('is_enabled', true) as $project)
        <div class="card p-4 mb-4">
            <h4 class="font-semibold text-slate-900 mb-2">{{ $project->name }} — полное восстановление</h4>
            <p class="text-xs font-mono text-slate-500 mb-3">{{ $project->root_path }}</p>
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                Сначала сделайте резервную копию текущих файлов. Команда ниже перезапишет каталог проекта.
            </p>
            <p class="text-sm text-slate-600 mb-1">Сразу на место:</p>
            <pre class="code-block text-xs mb-3">restic restore &lt;snapshot-id&gt; --target / \
  --include "{{ $project->root_path }}/**"</pre>
            <p class="text-sm text-slate-600 mb-1">Или через временную папку:</p>
            <pre class="code-block text-xs">SNAPSHOT=&lt;snapshot-id&gt;
TMP=~/restore-files-{{ $server->storageSlug($project->name) }}
mkdir -p "$TMP"

restic restore "$SNAPSHOT" --target "$TMP" --include "{{ $project->root_path }}/**"

rsync -a "$TMP{{ $project->root_path }}/" "{{ $project->root_path }}/"</pre>
            <p class="text-xs text-slate-400 mt-2 mb-0">Если <code>rsync</code> нет: <code>cp -a "$TMP{{ $project->root_path }}/." "{{ $project->root_path }}/"</code></p>
        </div>
    @endforeach

    <h3 class="font-medium text-slate-800 mb-2 mt-6">Последний снимок по тегу проекта</h3>
    @foreach($server->projects->where('is_enabled', true) as $project)
        <pre class="code-block text-xs mb-2">restic snapshots --tag project:{{ $server->storageSlug($project->name) }}</pre>
    @endforeach
</section>

<section class="mb-10" id="disaster">
    <h2 class="section-title">5. Новый пустой сервер (старый умер)</h2>
    <p class="text-sm text-slate-600 mb-4">
        Бэкапы лежат в облаке, не на сервере. Новый VPS/хостинг поднимаете с нуля, подключаете тот же
        restic-репозиторий и накатываете файлы + БД.
    </p>

    <h3 class="font-medium text-slate-800 mb-2">5.1. Что должно быть сохранено заранее</h3>
    <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside mb-4">
        <li>Slug: <code class="font-mono">{{ $server->repoSlug() }}</code></li>
        <li>Репозиторий: <code class="font-mono text-xs">{{ $server->resticRepository() }}</code></li>
        <li>Пароль: <code class="font-mono">{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}</code></li>
        <li>OAuth JSON токен rclone (Яндекс.Диск) — с шага 1 мастера</li>
        <li>Эта страница панели (или скрин) — пути проектов и имена БД</li>
    </ul>

    <h3 class="font-medium text-slate-800 mb-2">5.2. Подготовить новый сервер</h3>
    <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside mb-4">
        <li>Создайте сайт в панели хостинга (Hestia / Beget / и т.п.) — домен, <code>public_html</code>, PHP.</li>
        <li>Создайте пустую MySQL/MariaDB базу и пользователя (права на эту БД).</li>
        <li>Запомните новый путь сайта, например
            <code class="font-mono text-xs">/home/USER/web/DOMAIN/public_html</code>
            — он может отличаться от старого.</li>
        <li>SSH-доступ под пользователем сайта (не обязательно root).</li>
    </ol>

    <h3 class="font-medium text-slate-800 mb-2">5.3. Поставить restic + rclone и подключить старый репозиторий</h3>
    <p class="text-sm text-slate-600 mb-2">
        Важно: <strong>не делать <code>restic init</code></strong>, если репозиторий уже есть в облаке.
        Нужно только указать тот же путь и пароль.
    </p>
    <p class="text-sm text-slate-600 mb-2">Вариант A — через эту панель (удобнее):</p>
    <ol class="text-sm text-slate-600 space-y-1 list-decimal list-inside mb-3">
        <li>Добавьте новый сервер в панели, укажите SSH нового хоста.</li>
        <li>На шаге 1 вставьте тот же rclone token; пароль restic подставится сам.</li>
        <li>Убедитесь, что slug репозитория останется
            <code class="font-mono">{{ $server->repoSlug() }}</code>
            (иначе панель создаст <em>пустой</em> новый репозиторий).</li>
        <li>Нажмите «Установить restic» — на сервере появятся <code>~/backaper/</code> и доступ к облаку.</li>
        <li>Проверка: <code>source ~/backaper/backaper.env && restic snapshots</code> — должны быть старые снимки.</li>
    </ol>
    <p class="text-sm text-slate-600 mb-2">Вариант B — вручную на новом сервере:</p>
    <pre class="code-block text-xs"># 1) бинарники
mkdir -p ~/bin ~/backaper
export PATH="$HOME/bin:$PATH"

# restic
curl -fsSL https://github.com/restic/restic/releases/download/v0.16.4/restic_0.16.4_linux-amd64.bz2 \
  | bunzip2 > ~/bin/restic && chmod +x ~/bin/restic

# rclone
curl -fsSL https://downloads.rclone.org/rclone-current-linux-amd64.zip -o /tmp/rclone.zip
unzip -qo /tmp/rclone.zip -d /tmp && install -m 755 /tmp/rclone-*/rclone ~/bin/rclone

# 2) токен Яндекс.Диска (вставьте JSON одной строкой)
cat > ~/backaper/rclone-token.json <<'EOF'
{"access_token":"...","token_type":"bearer",...}
EOF
chmod 600 ~/backaper/rclone-token.json

rclone config create {{ $server->rclone_remote ?: 'yandex' }} yandex \
  config_token "$(tr -d '\n' < ~/backaper/rclone-token.json)" --non-interactive

# 3) env — тот же репозиторий, что был у старого сервера
cat > ~/backaper/backaper.env <<'EOF'
export RESTIC_REPOSITORY='{{ $server->resticRepository() }}'
export RESTIC_PASSWORD='{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}'
export BACKAPER_RCLONE_REMOTE='{{ $server->rclone_remote ?: 'yandex' }}'
export BACKAPER_CLOUD_PREFIX='{{ $server->cloudPrefix() }}'
export PATH="$HOME/bin:$PATH"
EOF
chmod 600 ~/backaper/backaper.env
ln -sf backaper.env ~/backaper/restic.env

# 4) проверка (без restic init!)
source ~/backaper/backaper.env
rclone lsd {{ $server->rclone_remote ?: 'yandex' }}:
restic snapshots</pre>
    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3 mb-6">
        Если <code>restic snapshots</code> пустой или ошибка пароля — неверный slug/репозиторий или пароль.
        Не запускайте <code>restic init</code>: это создаст другой репозиторий и старые снимки «пропадут» из виду.
    </p>

    <h3 class="font-medium text-slate-800 mb-2">5.4. Накатить файлы проекта</h3>
    <p class="text-sm text-slate-600 mb-3">
        В снимке пути абсолютные со <strong>старого</strong> сервера. На новом путь часто другой —
        поэтому восстанавливаем во временную папку и копируем в новый <code>public_html</code>.
    </p>
    @foreach($server->projects->where('is_enabled', true) as $project)
        <div class="card p-4 mb-4">
            <h4 class="font-semibold text-slate-900 mb-2">{{ $project->name }}</h4>
            <p class="text-xs font-mono text-slate-500 mb-3">Старый путь в снимке: {{ $project->root_path }}</p>
            <pre class="code-block text-xs">source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"

# последний снимок проекта
restic snapshots --tag project:{{ $server->storageSlug($project->name) }}
SNAPSHOT=&lt;snapshot-id&gt;

OLD="{{ $project->root_path }}"
NEW="/home/USER/web/DOMAIN/public_html"   # ← ваш новый путь
TMP=~/restore-full-{{ $server->storageSlug($project->name) }}

mkdir -p "$TMP" "$NEW"
restic restore "$SNAPSHOT" --target "$TMP" --include "$OLD/**"
rsync -a "$TMP$OLD/" "$NEW/"
# без rsync: cp -a "$TMP$OLD/." "$NEW/"</pre>
        </div>
    @endforeach
    @if($server->projects->where('is_enabled', true)->isEmpty())
        <pre class="code-block text-xs mb-4">SNAPSHOT=&lt;snapshot-id&gt;
OLD="/home/olduser/web/old-domain/public_html"
NEW="/home/USER/web/DOMAIN/public_html"
TMP=~/restore-full

mkdir -p "$TMP" "$NEW"
restic restore "$SNAPSHOT" --target "$TMP" --include "$OLD/**"
rsync -a "$TMP$OLD/" "$NEW/"</pre>
    @endif

    <h3 class="font-medium text-slate-800 mb-2">5.5. Накатить базу данных</h3>
    <pre class="code-block text-xs mb-2">source ~/backaper/backaper.env
rclone ls {{ $server->rclone_remote }}:{{ $server->cloudPrefix() }}/databases/
mkdir -p ~/restore
rclone copy {{ $server->rclone_remote }}:{{ $server->cloudPrefix() }}/databases/ИМЯ_БД/ИМЯ_ФАЙЛА.sql.gz ~/restore/

# создайте пустую БД заранее, затем:
gunzip -c ~/restore/ИМЯ_ФАЙЛА.sql.gz | \
  mariadb -h localhost -u DB_USER --password='DB_PASS' DB_NAME</pre>
    @foreach($server->projects->where('is_enabled', true) as $project)
        @if($project->database)
            <p class="text-xs text-slate-500 mb-2">
                {{ $project->name }}: старая БД
                <code class="font-mono">{{ $project->database->database_name }}</code>,
                пользователь <code class="font-mono">{{ $project->database->database_user }}</code>
                (на новом сервере имена могут быть другими — подставьте свои).
            </p>
        @endif
    @endforeach

    <h3 class="font-medium text-slate-800 mb-2 mt-4">5.6. Починить MODX и проверить сайт</h3>
    <ol class="text-sm text-slate-600 space-y-2 list-decimal list-inside mb-2">
        <li>Откройте <code>core/config/config.inc.php</code> в новом <code>public_html</code>.</li>
        <li>Пропишите новые: хост БД, имя БД, логин, пароль; при необходимости пути
            (<code>$modx_base_path</code>, <code>$modx_core_path</code>, URL сайта).</li>
        <li>Права: обычно пользователь сайта должен владеть файлами
            (<code>chown -R USER:USER "$NEW"</code>).</li>
        <li>Удалите содержимое <code>core/cache/</code>.</li>
        <li>Откройте сайт и менеджер MODX; при необходимости пересоздайте виртуальный хост / SSL.</li>
    </ol>
    <p class="text-sm text-slate-500 mt-3 mb-0">
        Когда сайт жив — в панели заведите этот хост как сервер, прогоните мастер и
        «Установить restic» с <strong>тем же slug</strong>
        <code class="font-mono">{{ $server->repoSlug() }}</code>,
        чтобы следующие бэкапы снова писались в тот же облачный репозиторий.
    </p>
</section>

<section class="mb-10">
    <h2 class="section-title">6. После восстановления</h2>
    <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
        <li>Проверьте <code>core/config/config.inc.php</code> — хост, логин и пароль БД, пути и URL.</li>
        <li>Очистите кэш MODX: удалите содержимое <code>core/cache/</code> (он не входит в бэкап).</li>
        <li>Откройте сайт в браузере и проверьте менеджер MODX.</li>
    </ul>
</section>
@endsection
