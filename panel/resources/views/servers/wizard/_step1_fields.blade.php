<div>
    <label class="label">RESTIC_PASSWORD</label>
    <input name="restic_password" type="password"
           value="{{ old('restic_password', $server->restic_password ?? '') }}"
           required minlength="8"
           class="input @error('restic_password') input-error @enderror">
    @error('restic_password')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    <p class="text-xs text-slate-400 mt-1">Пароль шифрования restic — придумайте и сохраните.</p>
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
</div>

@include('servers._rclone_help')

<p class="text-xs text-slate-500">После мастера на странице сервера нажмите «Установить restic» — панель сама передаст настройки по SSH.</p>
