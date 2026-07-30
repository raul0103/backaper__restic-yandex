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
        Панель ставит restic и ставит серверы в очередь. Если нужно запустить руками по SSH — команды ниже.
    </p>
</div>

<div class="help-box mb-6">
    Массовый запуск из панели: <a href="{{ route('servers.index') }}" class="text-brand-700 font-medium underline">Серверы</a>
    → отметьте хосты → «Запустить очередь».
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
        <h2 class="section-title">3. Screen — смотреть прогресс и не оборвать SSH</h2>
        <p class="text-sm text-slate-500 mb-3">
            Бэкап может идти долго. Запускайте его внутри <code>screen</code>: лог виден в терминале,
            а при обрыве SSH процесс не умирает.
        </p>
        <ul class="text-sm text-slate-600 space-y-2 mb-4 list-disc pl-5">
            <li><strong class="text-slate-900">Смотреть прогресс</strong> — оставайтесь в сессии: вывод restic/дампа идёт прямо в окно. Не запускайте повторно <code>screen -r</code> изнутри той же сессии.</li>
            <li><strong class="text-slate-900">Проверить, внутри ли вы</strong>: <code>echo $STY</code> — если непусто (например <code>850591.backup-files</code>), вы уже в screen.</li>
            <li><strong class="text-slate-900">Отсоединиться</strong> (бэкап продолжается): <code>Ctrl+A</code>, затем <code>D</code>.</li>
            <li><strong class="text-slate-900">Вернуться смотреть лог</strong>: <code>screen -r backup-files</code> или <code>screen -r backup-db</code>.</li>
            <li><strong class="text-slate-900">Список сессий</strong>: <code>screen -ls</code>.</li>
            <li><strong class="text-slate-900">Уже Attached / «Attaching from inside of screen?»</strong> — вы внутри этой сессии; либо смотрите лог здесь, либо <code>Ctrl+A</code> <code>D</code>. Из другого SSH: <code>screen -d -r backup-files</code>.</li>
            <li><strong class="text-slate-900">Листать старый вывод</strong>: <code>Ctrl+A</code>, затем <code>[</code>, стрелки / PageUp; выход — <code>Esc</code>.</li>
            <li><strong class="text-slate-900">Закрыть сессию</strong> после окончания бэкапа: <code>exit</code> или <code>screen -S backup-files -X quit</code>.</li>
        </ul>
        <pre class="code-block">echo $STY
screen -ls
screen -r backup-files
screen -d -r backup-files</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">4. Бэкап файлов</h2>
        <p class="text-sm text-slate-500 mb-3">
            Restic-снапшот: на VPS — весь сервер (<code>/</code>), на хостинге — домашний каталог (<code>~</code>).
            Upload ограничен (~2 MiB/s). Базы сюда не входят.
            Прогресс — в том же окне screen, пока не нажмёте <code>Ctrl+A</code> <code>D</code>.
        </p>
        <pre class="code-block mb-3">screen -S backup-files
source ~/backaper/backaper.env
# VPS: root=/ ; Hosting: root=$HOME
bash ~/backaper/scripts/backup-files.sh</pre>
        <p class="text-sm text-slate-600 mb-2">Медленнее (если убивает процесс):</p>
        <pre class="code-block">BACKAPER_UPLOAD_LIMIT_KIB=1024 bash ~/backaper/scripts/backup-files.sh</pre>
        <p class="text-xs text-slate-500 mt-3">Из бэкапа пропускаются: <code>core/cache</code>, <code>node_modules</code>, <code>.git</code>, <code>vendor</code>.</p>
    </section>

    <section class="card p-6">
        <h2 class="section-title">5. Дамп баз</h2>
        <p class="text-sm text-slate-500 mb-3">
            Скрипт сам находит <code>config.inc.php</code> / <code>wp-config.php</code> / <code>.env</code>,
            парсит доступы и заливает <code>.sql.gz</code> на Диск в <code>databases/{сервер}/{db}/</code>.
            Прогресс — строки <code>[db] OK …</code> в окне screen.
        </p>
        <pre class="code-block mb-3">screen -S backup-db
source ~/backaper/backaper.env
bash ~/backaper/scripts/backup-databases.sh</pre>
        <p class="text-xs text-slate-500">Нужен <code>php-cli</code> на сервере для разбора конфигов.</p>
    </section>

    <section class="card p-6">
        <h2 class="section-title">6. Проверка</h2>
        <pre class="code-block">source ~/backaper/backaper.env
ps aux | grep -E 'restic|mysqldump|rclone' | grep -v grep
restic snapshots --tag path:home
rclone lsl "${BACKAPER_RCLONE_REMOTE}:${BACKAPER_CLOUD_PREFIX}/" | tail -20</pre>
    </section>

    <section class="card p-6">
        <h2 class="section-title">7. Если скриптов нет</h2>
        <p class="text-sm text-slate-500 mb-3">
            Откройте сервер → «Изменить» или шаг установки и нажмите «Переустановить restic» —
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
