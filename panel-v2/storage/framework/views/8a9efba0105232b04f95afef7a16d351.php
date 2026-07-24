<?php $__env->startSection('title', $server->name); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div>
        <h1 class="page-title"><?php echo e($server->name); ?></h1>
        <p class="page-subtitle">
            <span class="badge badge-info"><?php echo e($server->kindLabel()); ?></span>
            <span class="ml-2 font-mono text-xs"><?php echo e($server->ssh_user); ?>{{ $server->host }}:<?php echo e($server->ssh_port); ?></span>
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if($server->readyForBackup()): ?>
            <form method="post" action="<?php echo e(route('servers.backup', $server)); ?>"><?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-primary">Запустить бэкап</button>
            </form>
        <?php endif; ?>
        <?php if($server->readyForRemoteSetup()): ?>
            <form method="post" action="<?php echo e(route('servers.setup', $server)); ?>"><?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-secondary">
                    <?php echo e($server->is_setup_complete ? 'Переустановить restic' : 'Установить restic'); ?>

                </button>
            </form>
        <?php endif; ?>
        <a href="<?php echo e(route('servers.restore', $server)); ?>" class="btn btn-secondary">Восстановление</a>
        <a href="<?php echo e(route('servers.edit', $server)); ?>" class="btn btn-secondary">Изменить</a>
        <a href="<?php echo e(route('servers.wizard.content', $server)); ?>" class="btn btn-ghost">Базы данных</a>
    </div>
</div>

<?php if(! $server->is_setup_complete): ?>
    <div class="alert alert-warning mb-6">
        restic не готов (удалили папки на Диске, сменили пароль/токен/папку, или ещё не ставили).
        Нажмите <strong>«Установить restic»</strong> / <strong>«Переустановить restic»</strong> выше.
    </div>
<?php endif; ?>

<div class="grid sm:grid-cols-3 gap-4 mb-8">
    <div class="card p-4">
        <div class="text-xs text-slate-500 font-medium">restic</div>
        <div class="font-semibold mt-1"><?php echo e($server->is_setup_complete ? 'Установлен' : 'Не установлен'); ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500 font-medium">Путей</div>
        <div class="font-semibold mt-1"><?php echo e($server->backupPaths->where('is_enabled', true)->count()); ?> / <?php echo e($server->backupPaths->count()); ?></div>
    </div>
    <div class="card p-4">
        <div class="text-xs text-slate-500 font-medium">Баз</div>
        <div class="font-semibold mt-1"><?php echo e($server->databases->where('is_enabled', true)->count()); ?> / <?php echo e($server->databases->count()); ?></div>
    </div>
</div>

<section class="mb-8">
    <h2 class="section-title">Файлы</h2>
    <div class="card p-4">
        <?php ($path = $server->backupPaths->first()); ?>
        <div class="font-medium"><?php echo e($path?->displayName() ?? $server->fullBackupTarget()['label']); ?></div>
        <div class="text-xs font-mono text-slate-400 mt-1"><?php echo e($path?->path ?? $server->fullBackupTarget()['path']); ?></div>
        <p class="text-xs text-slate-500 mt-2">Бэкапится целиком. Пути настраивать не нужно.</p>
    </div>
</section>

<section class="mb-8">
    <h2 class="section-title">Базы данных</h2>
    <div class="card divide-y divide-slate-100">
        <?php $__empty_1 = true; $__currentLoopData = $server->databases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $db): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="p-4 flex justify-between gap-3">
                <div>
                    <div class="font-medium font-mono"><?php echo e($db->database_name); ?></div>
                    <div class="text-xs text-slate-400 mt-0.5">
                        <?php echo e($db->database_server); ?> · <?php echo e($db->database_user); ?>

                        <?php if($db->source): ?> · <?php echo e($db->source); ?> <?php endif; ?>
                    </div>
                </div>
                <?php if($db->is_enabled): ?>
                    <span class="badge badge-success">вкл</span>
                <?php else: ?>
                    <span class="badge badge-warning">выкл</span>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-sm text-slate-400">Нет баз — откройте «Базы данных» и нажмите «Найти базы»</p>
        <?php endif; ?>
    </div>
</section>

<section>
    <h2 class="section-title">Последние бэкапы</h2>
    <?php $__empty_1 = true; $__currentLoopData = $server->backupRuns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $run): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('backup-runs.show', $run)); ?>" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3">
                <span class="font-medium">#<?php echo e($run->id); ?> · <?php echo e($run->started_at?->format('d.m.Y H:i')); ?></span>
                <span class="badge <?php echo e($run->status === 'completed' ? 'badge-success' : ($run->status === 'running' ? 'badge-info' : 'badge-warning')); ?>"><?php echo e($run->status); ?></span>
            </div>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="text-sm text-slate-400">Пока не запускали</p>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/show.blade.php ENDPATH**/ ?>