<?php $__env->startSection('title', 'Шаг 3 — '.$server->name); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('servers.wizard._steps', ['current' => 3, 'server' => $server], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="mb-8">
    <h1 class="page-title">Шаг 3 — Базы данных</h1>
    <p class="page-subtitle">Файлы бэкапятся целиком автоматически. Здесь только дампы БД.</p>
</div>

<div class="help-box mb-6">
    <?php if($server->isHosting()): ?>
        <strong>Файлы:</strong> весь аккаунт хостинга (домашняя папка пользователя).
    <?php else: ?>
        <strong>Файлы:</strong> весь сервер (<code>/</code>), без системных каталогов вроде /proc, /sys, /tmp.
    <?php endif; ?>
    <br>
    Из сайтов пропускаются: cache, node_modules, .git, vendor.
    Пути указывать не нужно.
</div>

<form method="post" action="<?php echo e(route('servers.wizard.content.finish', $server)); ?>" class="space-y-6">
    <?php echo csrf_field(); ?>

    <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="section-title !mb-1">Базы данных (отдельные дампы)</h2>
                <p class="text-sm text-slate-500">Найдите конфиги сайтов и отметьте, какие базы сохранять отдельно.</p>
            </div>
            <button type="submit" formaction="<?php echo e(route('servers.wizard.discover-databases', $server)); ?>" class="btn btn-secondary">Найти базы</button>
        </div>

        <?php if($server->databases->isEmpty()): ?>
            <p class="text-sm text-slate-400">Баз пока нет. Нажмите «Найти базы» — или завершите настройку: файлы всё равно будут бэкапиться.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php $__currentLoopData = $server->databases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $db): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50">
                        <input type="checkbox" name="databases[<?php echo e($db->id); ?>][enabled]" value="1" <?php if($db->is_enabled): echo 'checked'; endif; ?> class="mt-1 w-4 h-4">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900 font-mono"><?php echo e($db->database_name); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                Хост: <span class="font-mono"><?php echo e($db->database_server); ?></span>
                                · пользователь: <span class="font-mono"><?php echo e($db->database_user); ?></span>
                                <?php if($db->source): ?>
                                    · <?php echo e($db->source); ?>

                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn btn-primary">Завершить настройку</button>
        <a href="<?php echo e(route('servers.wizard.install', $server)); ?>" class="btn btn-secondary">← Назад</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/wizard/content.blade.php ENDPATH**/ ?>