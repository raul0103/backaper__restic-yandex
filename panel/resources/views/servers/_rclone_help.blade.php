<details class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
    <summary class="cursor-pointer font-medium text-slate-900">Как получить Rclone OAuth token (один раз на Яндекс.Диск)</summary>
    <div class="mt-3 space-y-3">
        <p>Токен получают <strong>на вашем компьютере</strong> (не на сервере с сайтами). Нужен установленный <a href="https://rclone.org/downloads/" class="text-brand-700 underline" target="_blank" rel="noopener">rclone</a>.</p>
        <ol class="list-decimal list-inside space-y-2">
            <li>
                <strong>Перед командой</strong> войдите в нужный аккаунт на
                <a href="https://passport.yandex.ru" class="text-brand-700 underline" target="_blank" rel="noopener">passport.yandex.ru</a>
                (Яндекс ID / Passport) — <em>именно туда попадут бэкапы</em>.
                Выход из Почты или Диска не всегда означает выход из Passport: проверьте аватар и логин на странице паспорта.
            </li>
            <li>Откройте терминал (PowerShell / cmd) на Windows или Terminal на Mac/Linux.</li>
            <li>Выполните:
                <pre class="code-block mt-2 mb-0">rclone authorize "yandex"</pre>
            </li>
            <li>
                Откроется браузер с запросом доступа. Убедитесь, что вы <strong>авторизованы в Passport под нужным аккаунтом</strong>.
                Если аккаунт не тот — выйдите на
                <a href="https://passport.yandex.ru" class="text-brand-700 underline" target="_blank" rel="noopener">passport.yandex.ru</a>
                или откройте ссылку в режиме инкognito, войдите под правильным логином, затем нажмите «Разрешить» для rclone.
            </li>
            <li>В терминале появится JSON целиком, например:
                <pre class="code-block mt-2 mb-0 text-xs">{"access_token":"...","token_type":"bearer","refresh_token":"...","expiry":"..."}</pre>
            </li>
            <li>Скопируйте <strong>весь JSON</strong> и вставьте в поле «Rclone OAuth token» ниже.</li>
        </ol>
        <p class="text-xs text-slate-500">
            Проверка токена (необязательно):
            <code class="bg-white px-1 rounded">rclone config create yandex-check yandex config_token (Get-Content token.json -Raw)</code>,
            затем <code class="bg-white px-1 rounded">rclone about yandex-check:</code> — объём диска должен совпасть с нужным аккаунтом.
        </p>
        <p class="text-xs text-slate-500 mb-0">
            Панель сохранит токен в БД. Чтобы бэкапы шли в новый аккаунт, после сохранения нажмите на странице сервера
            <strong>«Переустановить restic»</strong> — токен будет залит на сервер по SSH. Токен — секрет, не публикуйте его.
        </p>
    </div>
</details>
