@extends('layouts.app')

@section('title', 'Как пользоваться')

@section('content')
<div class="mb-8">
    <h1 class="page-title">Как пользоваться Backaper v2</h1>
    <p class="page-subtitle">Бэкап VPS и хостингов на Яндекс.Диск: файлы целиком + отдельные дампы баз данных.</p>
</div>

<div class="space-y-6">
    <section class="card p-6">
        <h2 class="section-title">Что делает программа</h2>
        <ol class="list-decimal pl-5 space-y-2 text-slate-600 text-sm leading-relaxed">
            <li><strong class="text-slate-900">Файлы</strong> — копирует указанные папки целиком (весь аккаунт хостинга или сайты на VPS).</li>
            <li><strong class="text-slate-900">Базы данных</strong> — находит доступы в конфигах сайтов (MODX, WordPress, Laravel) и сохраняет дампы отдельно.</li>
            <li>Всё шифруется и лежит на <strong class="text-slate-900">Яндекс.Диске</strong>.</li>
        </ol>
        <p class="text-sm text-slate-500 mt-4">Из бэкапа файлов всегда пропускаются: <code class="text-xs bg-slate-100 px-1 rounded">core/cache</code>, <code class="text-xs bg-slate-100 px-1 rounded">node_modules</code>, <code class="text-xs bg-slate-100 px-1 rounded">.git</code>, <code class="text-xs bg-slate-100 px-1 rounded">vendor</code>.</p>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Три шага настройки</h2>
        <div class="space-y-4">
            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 font-bold flex items-center justify-center shrink-0">1</div>
                <div>
                    <h3 class="font-semibold text-slate-900">Подключение</h3>
                    <p class="text-sm text-slate-600 mt-1">Укажите тип (VPS или хостинг), SSH-доступ и токен Яндекс.Диска. Пароль шифрования можно оставить по умолчанию.</p>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 font-bold flex items-center justify-center shrink-0">2</div>
                <div>
                    <h3 class="font-semibold text-slate-900">Установка</h3>
                    <p class="text-sm text-slate-600 mt-1">Одна кнопка ставит на сервер программы restic и rclone. Ничего руками на сервере писать не нужно.</p>
                </div>
            </div>
            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 font-bold flex items-center justify-center shrink-0">3</div>
                <div>
                    <h3 class="font-semibold text-slate-900">Базы данных</h3>
                    <p class="text-sm text-slate-600 mt-1">Файлы бэкапятся сами (весь хостинг или весь VPS). Нажмите «Найти базы» и отметьте нужные — они сохранятся отдельными дампами.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Откуда взять токен Яндекс.Диска</h2>
        <ol class="list-decimal pl-5 space-y-2 text-sm text-slate-600">
            <li>На своём компьютере установите <a class="text-brand-700 underline" href="https://rclone.org/downloads/" target="_blank" rel="noopener">rclone</a>.</li>
            <li>В терминале выполните: <code class="bg-slate-100 px-1 rounded text-xs">rclone authorize "yandex"</code></li>
            <li>Войдите в Яндекс в браузере и скопируйте JSON-токен в поле панели.</li>
        </ol>
    </section>

    <section class="card p-6">
        <h2 class="section-title">После настройки</h2>
        <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
            <li><strong class="text-slate-900">Файлы</strong> — вручную по SSH (вкладка «Ручной запуск» → <code class="text-xs bg-slate-100 px-1 rounded">backup-files.sh</code>).</li>
            <li><strong class="text-slate-900">Базы</strong> — кнопка «Дамп баз» в панели или CLI <code class="text-xs bg-slate-100 px-1 rounded">backup-databases.sh</code> (ищет конфиги на лету).</li>
            <li>Инструкция по восстановлению — кнопка «Восстановление» у сервера.</li>
        </ul>
    </section>

    <section class="card p-6">
        <h2 class="section-title">Переустановка restic</h2>
        <p class="text-sm text-slate-600 mb-3">Нужна, если:</p>
        <ul class="list-disc pl-5 space-y-1 text-sm text-slate-600">
            <li>удалили папки бэкапа на Яндекс.Диске;</li>
            <li>сменили пароль шифрования, токен или имя папки в настройках сервера.</li>
        </ul>
        <p class="text-sm text-slate-600 mt-3">На странице сервера нажмите <strong>«Переустановить restic»</strong> — панели сама пересоздаст репозиторий.</p>
    </section>

    <div class="pt-2">
        <a href="{{ route('servers.create') }}" class="btn btn-primary">Добавить первый сервер</a>
    </div>
</div>
@endsection
