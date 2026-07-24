<?php $__env->startSection('title', 'Новый сервер'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-8">
    <h1 class="page-title">Добавить сервер</h1>
    <p class="page-subtitle">VPS или хостинг — бэкап файлов целиком и отдельные дампы баз на Яндекс.Диск.</p>
</div>

<div class="help-box mb-6">
    Не знаете с чего начать? Откройте <a href="<?php echo e(route('guide')); ?>" class="text-brand-700 font-medium underline">краткую инструкцию</a>.
</div>

<form method="post" action="<?php echo e(route('servers.store')); ?>" class="card p-6 space-y-5">
    <?php echo csrf_field(); ?>

    <div>
        <label class="label">Название (для вас)</label>
        <input name="name" value="<?php echo e(old('name')); ?>" required placeholder="Хостинг betonmash" class="input">
    </div>

    <div>
        <label class="label">Тип сервера</label>
        <div class="grid sm:grid-cols-2 gap-3">
            <label class="card p-4 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="kind" value="hosting" class="mr-2" <?php if(old('kind', 'hosting') === 'hosting'): echo 'checked'; endif; ?>>
                <span class="font-semibold">Хостинг</span>
                <p class="text-xs text-slate-500 mt-1">Beget, Timeweb и т.п. Бэкапим весь аккаунт (~).</p>
            </label>
            <label class="card p-4 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input type="radio" name="kind" value="vps" class="mr-2" <?php if(old('kind') === 'vps'): echo 'checked'; endif; ?>>
                <span class="font-semibold">VPS</span>
                <p class="text-xs text-slate-500 mt-1">Свой сервер. Обычно /var/www и /home.</p>
            </label>
        </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
            <label class="label">SSH хост</label>
            <input name="host" value="<?php echo e(old('host')); ?>" required placeholder="server.beget.com или IP" class="input">
        </div>
        <div>
            <label class="label">Порт</label>
            <input name="ssh_port" type="number" value="<?php echo e(old('ssh_port', 22)); ?>" required class="input">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label">SSH логин</label>
            <input name="ssh_user" value="<?php echo e(old('ssh_user')); ?>" required placeholder="betonmash" class="input">
        </div>
        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" required class="input" autocomplete="off">
        </div>
    </div>

    <div>
        <label class="label">Пароль шифрования бэкапов</label>
        <input name="restic_password" type="text" value="<?php echo e(old('restic_password', \App\Models\Server::DEFAULT_RESTIC_PASSWORD)); ?>" required class="input font-mono text-sm">
        <p class="text-xs text-slate-400 mt-1">Запомните его — без него бэкапы не открыть.</p>
    </div>

    <div>
        <label class="label">Имя папки на Яндекс.Диске</label>
        <input name="restic_repo_slug" id="restic_repo_slug" value="<?php echo e(old('restic_repo_slug')); ?>" placeholder="заполнится из названия" class="input font-mono text-sm">
    </div>

    <div>
        <label class="label">Токен Яндекс.Диска (JSON)</label>
        <textarea name="rclone_token" rows="3" required class="textarea font-mono !text-xs" placeholder='{"access_token":"...","token_type":"bearer",...}'><?php echo e(old('rclone_token')); ?></textarea>
        <p class="text-xs text-slate-400 mt-1">Команда: <code>rclone authorize "yandex"</code> — подробнее в <a href="<?php echo e(route('guide')); ?>" class="underline">инструкции</a>.</p>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary">Сохранить и продолжить</button>
        <a href="<?php echo e(route('servers.index')); ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<script>
(function () {
    var nameInput = document.querySelector('input[name="name"]');
    var slugInput = document.getElementById('restic_repo_slug');
    if (!nameInput || !slugInput) return;
    function toSlug(v) { return (v || '').replace(/[^a-zA-Z0-9._-]+/g, '-').replace(/^-+|-+$/g, '') || ''; }
    var manual = slugInput.value !== '';
    slugInput.addEventListener('input', function () { manual = true; });
    nameInput.addEventListener('input', function () {
        if (!manual || slugInput.value === '') { slugInput.value = toSlug(nameInput.value); manual = false; }
    });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/create.blade.php ENDPATH**/ ?>