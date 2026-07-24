<div>
    <label class="label">RESTIC_PASSWORD</label>
    <input type="text" value="{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}" readonly
           class="input font-mono bg-slate-50">
    <p class="text-xs text-slate-500 mt-1.5 mb-0">
        Один пароль шифрования для всех серверов и проектов:
        <code class="font-mono">{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}</code>
    </p>
</div>

<div>
    <label class="label">Slug репозитория (облако)</label>
    <input name="restic_repo_slug"
           value="{{ old('restic_repo_slug', $server->restic_repo_slug ?? $server->repoSlug()) }}"
           class="input font-mono text-sm"
           pattern="[A-Za-z0-9._\-]+"
           title="Латиница, цифры, точка, дефис, подчёркивание">
    <p class="text-xs text-slate-500 mt-1.5 mb-0">
        Путь в облаке: <code>restic-repo/SLUG</code> и <code>backaper/SLUG</code>.
        При переносе на новый сервер укажите <strong>тот же</strong> slug, иначе создастся пустой репозиторий.
    </p>
</div>

<div>
    <label class="label">Rclone remote</label>
    <input name="rclone_remote"
           value="{{ old('rclone_remote', $server->rclone_remote ?? 'yandex') }}"
           class="input">
    @if(isset($server) && $server->exists)
        <p class="text-xs text-slate-400 mt-1">Папки в облаке: <code>backaper/{{ $server->repoSlug() }}/…</code></p>
    @endif
</div>

<div>
    <label class="label">Rclone OAuth token (JSON)</label>
    <textarea name="rclone_token" rows="4" class="textarea font-mono !text-xs"
              placeholder='{"access_token":"...","token_type":"bearer",...}'>{{ old('rclone_token', $server->rclone_token ?? '') }}</textarea>
    @if(!empty($server->rclone_token) && !old('rclone_token'))
        <p class="text-xs text-brand-700 mt-1">Токен уже сохранён. Вставьте новый JSON только если нужно заменить.</p>
    @endif
    <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2 mb-0">
        <strong>После смены токена</strong> откройте страницу сервера и нажмите «Переустановить restic» — иначе на сервере останется старый аккаунт Яндекс.Диска.
    </p>
</div>

@include('servers._rclone_help')

<p class="text-xs text-slate-500">После мастера на странице сервера нажмите «Установить restic». При смене токена — «Переустановить restic».</p>
