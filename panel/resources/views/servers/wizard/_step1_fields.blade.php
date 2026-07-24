<div>
    <label class="label">Пароль шифрования бэкапов</label>
    <input name="restic_password" type="text"
           value="{{ old('restic_password', $server->restic_password ?: \App\Models\Server::DEFAULT_RESTIC_PASSWORD) }}"
           required minlength="8"
           class="input font-mono text-sm @error('restic_password') input-error @enderror">
    @error('restic_password')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    <p class="text-xs text-slate-500 mt-1.5 mb-0">
        По умолчанию <code class="font-mono">{{ \App\Models\Server::DEFAULT_RESTIC_PASSWORD }}</code>.
        Если папка бэкапов уже есть в облаке — укажите пароль, с которым она создавалась.
    </p>
</div>

<div>
    <label class="label">Имя папки бэкапов</label>
    <input name="restic_repo_slug" id="restic_repo_slug"
           value="{{ old('restic_repo_slug', $server->restic_repo_slug) }}"
           class="input font-mono text-sm"
           pattern="[A-Za-z0-9._\-]+"
           title="Латиница, цифры, точка, дефис, подчёркивание"
           placeholder="подставится из названия сервера"
           data-slug-manual="{{ old('restic_repo_slug', $server->restic_repo_slug) ? '1' : '0' }}">
    <p class="text-xs text-slate-500 mt-1.5 mb-0">
        Папка на Яндекс.Диске. Пока не меняли вручную — совпадает с <strong>названием сервера</strong>.
        При переносе на новый хост укажите <strong>то же имя</strong>, иначе старые бэкапы не найдутся.
        @if(isset($server) && $server->exists)
            Сейчас: <code class="font-mono">{{ $server->repoSlug() }}</code>
        @endif
    </p>
</div>

<input type="hidden" name="rclone_remote" value="{{ old('rclone_remote', $server->rclone_remote ?? 'yandex') }}">

<div>
    <label class="label">Токен Яндекс.Диска (JSON)</label>
    <textarea name="rclone_token" rows="3" class="textarea font-mono !text-xs"
              placeholder='{"access_token":"...","token_type":"bearer",...}'>{{ old('rclone_token', $server->rclone_token ?? '') }}</textarea>
    @if(!empty($server->rclone_token) && !old('rclone_token'))
        <p class="text-xs text-brand-700 mt-1">Токен уже сохранён. Вставьте новый JSON только чтобы заменить.</p>
    @endif
    <p class="text-xs text-slate-500 mt-1.5 mb-0">
        После смены токена на странице сервера нажмите «Переустановить restic».
    </p>
</div>

@include('servers._rclone_help')

<p class="text-xs text-slate-500">Сохраните настройки и нажмите «Установить restic» — проекты для этого не нужны.</p>

<script>
(function () {
    var nameInput = document.querySelector('input[name="name"]');
    var slugInput = document.getElementById('restic_repo_slug');
    if (!nameInput || !slugInput) return;

    function toSlug(value) {
        return String(value || '')
            .trim()
            .replace(/[^a-zA-Z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 120);
    }

    function syncFromName() {
        if (slugInput.getAttribute('data-slug-manual') === '1') return;
        slugInput.value = toSlug(nameInput.value);
    }

    slugInput.addEventListener('input', function () {
        slugInput.setAttribute('data-slug-manual', slugInput.value.trim() ? '1' : '0');
    });

    nameInput.addEventListener('input', syncFromName);
    if (!slugInput.value) syncFromName();
})();
</script>
