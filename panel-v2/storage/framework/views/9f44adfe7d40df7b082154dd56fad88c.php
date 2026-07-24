<?php $__env->startSection('title', 'Изменить — '.$server->name); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-8">
    <h1 class="page-title">Изменить сервер</h1>
</div>

<form method="post" action="<?php echo e(route('servers.update', $server)); ?>" class="card p-6 space-y-5">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>
    <div>
        <label class="label">Название</label>
        <input name="name" value="<?php echo e(old('name', $server->name)); ?>" required class="input">
    </div>
    <div>
        <label class="label">Тип</label>
        <select name="kind" class="input">
            <option value="hosting" <?php if(old('kind', $server->kind) === 'hosting'): echo 'selected'; endif; ?>>Хостинг</option>
            <option value="vps" <?php if(old('kind', $server->kind) === 'vps'): echo 'selected'; endif; ?>>VPS</option>
        </select>
    </div>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
            <label class="label">Host</label>
            <input name="host" value="<?php echo e(old('host', $server->host)); ?>" required class="input">
        </div>
        <div>
            <label class="label">Порт</label>
            <input name="ssh_port" type="number" value="<?php echo e(old('ssh_port', $server->ssh_port)); ?>" required class="input">
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="label">SSH пользователь</label>
            <input name="ssh_user" value="<?php echo e(old('ssh_user', $server->ssh_user)); ?>" required class="input">
        </div>
        <div>
            <label class="label">SSH пароль</label>
            <input name="ssh_password" type="password" placeholder="Не менять" class="input" autocomplete="off">
        </div>
    </div>
    <div>
        <label class="label">Пароль шифрования</label>
        <input name="restic_password" value="<?php echo e(old('restic_password', $server->restic_password)); ?>" required class="input">
    </div>
    <div>
        <label class="label">Папка на Диске</label>
        <input name="restic_repo_slug" value="<?php echo e(old('restic_repo_slug', $server->restic_repo_slug)); ?>" class="input font-mono text-sm">
    </div>
    <div>
        <label class="label">Токен Яндекс.Диска (оставьте пустым, чтобы не менять)</label>
        <textarea name="rclone_token" rows="3" class="textarea font-mono !text-xs"><?php echo e(old('rclone_token')); ?></textarea>
    </div>
    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?php echo e(route('servers.show', $server)); ?>" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<form method="post" action="<?php echo e(route('servers.destroy', $server)); ?>" class="mt-8" onsubmit="return confirm('Удалить сервер из панели?')">
    <?php echo csrf_field(); ?>
    <?php echo method_field('DELETE'); ?>
    <button type="submit" class="btn btn-danger">Удалить сервер</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/edit.blade.php ENDPATH**/ ?>