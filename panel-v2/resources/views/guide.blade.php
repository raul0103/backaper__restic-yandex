@extends('layouts.app')

@section('title', 'Ручной запуск')

@section('nav_map')
    <a href="{{ route('dashboard') }}">Панель</a>
    <span class="sep">→</span>
    <a href="{{ route('servers.index') }}">Серверы</a>
    <span class="sep">→</span>
    <strong class="text-slate-700">Ручной запуск</strong>
@endsection

@section('content')
<div class="mb-8">
    <h1 class="page-title">Ручной запуск бэкапа</h1>
    <p class="page-subtitle">
        Панель ставит restic и запускает очереди. Ручной запуск — по SSH командами ниже.
        На VPS предпочтительно через <strong>systemd-run</strong>: обрыв SSH не остановит restic.
    </p>
</div>

<div class="help-box mb-6">
    Массовый запуск из панели: <a href="{{ route('servers.index') }}" class="text-brand-700 font-medium underline">Серверы</a>
    → отметьте хосты → «Запустить очередь». Панель сама использует systemd-run (если доступен), иначе screen/nohup.
</div>

<div class="space-y-6">
    <section class="card p-6">
        <h2 class="section-title">1. Подключение</h2>
        <p class="text-sm text-slate-500 mb-3">Host, пользователь и порт — в настройках сервера в панели.</p>
        <pre class="code-block">ssh USER@HOST
# если порт не 22:
ssh USER@HOST -p PORT</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">2. Окружение</h2>
        <pre class="code-block">source ~/backaper/backaper.env</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">3. Запуск через systemd (рекомендуется на VPS)</h2>
        <p class="text-sm text-slate-500 mb-3">
            Одноразовый unit переживает закрытие SSH. Лог и код завершения можно смотреть отдельно.
            Нужны root (или systemd --user) и команда <code>systemd-run</code>.
        </p>

        <h3 class="font-semibold text-slate-900 mb-2">Файлы</h3>
        <pre class="code-block mb-4">source ~/backaper/backaper.env

systemd-run \
  --unit=restic-backup-files \
  --collect \
  --working-directory="$HOME" \
  --property=Type=oneshot \
  /bin/bash -c 'source ~/backaper/backaper.env; exec bash ~/backaper/scripts/backup-files.sh'

# SSH можно закрывать. Прогресс:
journalctl -u restic-backup-files -f
# или статус:
systemctl status restic-backup-files</pre>

        <h3 class="font-semibold text-slate-900 mb-2">Базы</h3>
        <pre class="code-block mb-4">source ~/backaper/backaper.env

systemd-run \
  --unit=restic-backup-db \
  --collect \
  --working-directory="$HOME" \
  --property=Type=oneshot \
  /bin/bash -c 'source ~/backaper/backaper.env; exec bash ~/backaper/scripts/backup-databases.sh'

journalctl -u restic-backup-db -f</pre>

        <p class="text-sm text-slate-600 mb-2">Медленнее (если провайдер убивает процесс):</p>
        <pre class="code-block mb-3">systemd-run \
  --unit=restic-backup-files \
  --collect \
  --working-directory="$HOME" \
  --property=Type=oneshot \
  /bin/bash -c 'source ~/backaper/backaper.env; export BACKAPER_UPLOAD_LIMIT_KIB=1024; exec bash ~/backaper/scripts/backup-files.sh'</pre>

        <ul class="text-sm text-slate-600 space-y-1 list-disc pl-5">
            <li><strong class="text-slate-900">Остановить</strong>: <code>systemctl stop restic-backup-files</code></li>
            <li><strong class="text-slate-900">Код выхода</strong>: <code>systemctl show -p ExecMainStatus --value restic-backup-files</code> (пока unit ещё виден; с <code>--collect</code> после успеха unit исчезает)</li>
            <li><strong class="text-slate-900">Нет systemd-run</strong> (часть хостингов) — используйте screen ниже.</li>
        </ul>
    </section>

    <section class="card p-6">
        <h2 class="section-title">4. Fallback: screen (хостинг без systemd)</h2>
        <p class="text-sm text-slate-500 mb-3">
            Если <code>systemd-run</code> недоступен — запускайте в screen, чтобы обрыв SSH не убил процесс.
        </p>
        <pre class="code-block mb-3">screen -S backup-files
source ~/backaper/backaper.env
bash ~/backaper/scripts/backup-files.sh
# отсоединиться: Ctrl+A, затем D
# вернуться: screen -r backup-files</pre>
        <pre class="code-block">screen -S backup-db
source ~/backaper/backaper.env
bash ~/backaper/scripts/backup-databases.sh</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">5. Что бэкапится</h2>
        <ul class="text-sm text-slate-600 space-y-2 list-disc pl-5">
            <li><strong class="text-slate-900">Файлы</strong>: VPS — весь сервер (<code>/</code>), хостинг — домашний каталог (<code>~</code>). Upload ~2 MiB/s. Базы сюда не входят.</li>
            <li><strong class="text-slate-900">Базы</strong>: скрипт ищет <code>config.inc.php</code> / <code>wp-config.php</code> / <code>.env</code>, дампит и заливает <code>.sql.gz</code> на Диск в <code>databases/{сервер}/{db}/</code>. Нужен <code>php-cli</code>.</li>
            <li>Из файлов пропускаются: <code>core/cache</code>, <code>node_modules</code>, <code>.git</code>, <code>vendor</code>.</li>
        </ul>
    </section>

    <section class="card p-6">
        <h2 class="section-title">6. Проверка</h2>
        <pre class="code-block">source ~/backaper/backaper.env
systemctl status restic-backup-files restic-backup-db 2>/dev/null
ps aux | grep -E 'restic|mysqldump|rclone' | grep -v grep
restic snapshots --tag path:home
restic snapshots --tag path:root
rclone lsl "${BACKAPER_RCLONE_REMOTE}:${BACKAPER_CLOUD_PREFIX}/" | tail -20</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">7. Если скриптов нет</h2>
        <p class="text-sm text-slate-500 mb-3">
            Откройте сервер → «Изменить» и нажмите «Переустановить restic» —
            скрипты зальются вместе с установкой.
        </p>
        <pre class="code-block">ls -la ~/backaper/scripts/backup-files.sh ~/backaper/scripts/backup-databases.sh</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Токен Яндекс.Диска</h2>
        <ol class="list-decimal pl-5 space-y-2 text-sm text-slate-600">
            <li>На своём компьютере установите <a class="text-brand-700 underline" href="https://rclone.org/downloads/" target="_blank" rel="noopener">rclone</a>.</li>
            <li>В терминале: <code class="bg-slate-100 px-1 rounded text-xs">rclone authorize "yandex"</code></li>
            <li>Войдите в Яндекс в браузере и скопируйте JSON-токен в поле панели.</li>
        </ol>
    </section>
</div>
@endsection
