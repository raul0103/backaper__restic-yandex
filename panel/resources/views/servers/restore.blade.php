@extends('layouts.app')

@section('title', 'Восстановление — '.$server->name)

@section('content')
<div class="mb-8">
    <a href="{{ route('servers.show', $server) }}" class="inline-flex items-center gap-1 text-brand-600 font-medium text-sm hover:underline no-underline mb-4">
        ← К серверу {{ $server->name }}
    </a>
    <h1 class="page-title">Восстановление бэкапа</h1>
    <p class="page-subtitle">{{ $server->ssh_user }}@{{ $server->host }} — команды выполняются на сервере по SSH</p>
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
    </dl>
    <p class="text-sm text-slate-500 mt-4 mb-0">
        Файлы в restic не видны как обычные папки на Яндекс.Диске — только через <code>restic</code> на сервере.
        Дампы БД — обычные файлы, их можно скачать через rclone или веб-интерфейс Диска.
    </p>
</div>

<section class="mb-10">
    <h2 class="section-title">1. Подключиться к серверу</h2>
    <pre class="code-block">ssh {{ $server->ssh_user }}@{{ $server->host }} -p {{ $server->ssh_port }}</pre>
    <p class="text-sm text-slate-500 mt-2">Загрузить переменные restic/rclone (нужны для всех команд ниже):</p>
    <pre class="code-block">source ~/backaper/backaper.env
export PATH="$HOME/bin:$PATH"</pre>
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

    <h3 class="font-medium text-slate-800 mb-2">Список снимков</h3>
    <pre class="code-block">restic snapshots</pre>

    <h3 class="font-medium text-slate-800 mb-2 mt-6">Содержимое снимка</h3>
    <pre class="code-block">restic ls &lt;snapshot-id&gt;</pre>
    <p class="text-sm text-slate-500 mb-4">Или только папку проекта:</p>
    @foreach($server->projects->where('is_enabled', true) as $project)
        <pre class="code-block text-xs mb-2">restic ls &lt;snapshot-id&gt; {{ $project->root_path }}</pre>
    @endforeach

    @foreach($server->projects->where('is_enabled', true) as $project)
        <div class="card p-4 mb-4">
            <h4 class="font-semibold text-slate-900 mb-2">{{ $project->name }} — полное восстановление</h4>
            <p class="text-xs font-mono text-slate-500 mb-3">{{ $project->root_path }}</p>
            <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                Сначала сделайте резервную копию текущих файлов. Команда ниже перезапишет каталог проекта.
            </p>
            <pre class="code-block text-xs">SNAPSHOT=&lt;snapshot-id&gt;
TMP=~/restore-files-{{ $server->storageSlug($project->name) }}
mkdir -p "$TMP"

restic restore "$SNAPSHOT" --target "$TMP" --include "{{ $project->root_path }}/**"

rsync -a "$TMP{{ $project->root_path }}/" "{{ $project->root_path }}/"</pre>
            <p class="text-xs text-slate-400 mt-2 mb-0">Если <code>rsync</code> нет: <code>cp -a "$TMP{{ $project->root_path }}/." "{{ $project->root_path }}/"</code></p>
        </div>
    @endforeach

    <h3 class="font-medium text-slate-800 mb-2 mt-6">Восстановить отдельные файлы</h3>
    <pre class="code-block text-xs">restic restore &lt;snapshot-id&gt; --target ~/restore-partial \
  --include "{{ $server->projects->first()?->root_path }}/core/config/config.inc.php"</pre>
    <p class="text-sm text-slate-500 mt-2">Флаг <code>--include</code> можно повторять. Путь — абсолютный, как в снимке.</p>

    <h3 class="font-medium text-slate-800 mb-2 mt-6">Последний снимок по тегу проекта</h3>
    @foreach($server->projects->where('is_enabled', true) as $project)
        <pre class="code-block text-xs mb-2">restic snapshots --tag project:{{ $server->storageSlug($project->name) }}</pre>
    @endforeach
</section>

<section class="mb-10">
    <h2 class="section-title">4. После восстановления</h2>
    <ul class="text-sm text-slate-600 space-y-2 list-disc list-inside">
        <li>Проверьте <code>core/config/config.inc.php</code> — хост, логин и пароль БД.</li>
        <li>Очистите кэш MODX: удалите содержимое <code>core/cache/</code> (он не входит в бэкап).</li>
        <li>Откройте сайт в браузере и проверьте менеджер MODX.</li>
    </ul>
</section>
@endsection
