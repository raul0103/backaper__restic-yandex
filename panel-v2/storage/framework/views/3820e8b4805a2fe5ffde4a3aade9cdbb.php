<?php $__env->startSection('title', 'Панель'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Панель</h1>
        <p class="page-subtitle">Backaper v2 — полный бэкап серверов и отдельные дампы БД</p>
    </div>
    <a href="<?php echo e(route('guide')); ?>" class="text-sm text-brand-700 font-medium underline">Как пользоваться</a>
</div>

<section class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="section-title !mb-0">Серверы</h2>
        <a href="<?php echo e(route('servers.create')); ?>" class="btn btn-primary !py-2">+ Сервер</a>
    </div>
    <?php $__empty_1 = true; $__currentLoopData = $servers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $server): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('servers.show', $server)); ?>" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3">
                <span class="font-semibold text-slate-900"><?php echo e($server->name); ?></span>
                <span class="text-xs text-slate-400"><?php echo e($server->kindLabel()); ?> · путей <?php echo e($server->backup_paths_count); ?> · баз <?php echo e($server->databases_count); ?></span>
            </div>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="card p-6 text-center text-slate-500 text-sm">
            Пока пусто. <a href="<?php echo e(route('servers.create')); ?>" class="text-brand-700 underline">Добавьте сервер</a>
            или прочитайте <a href="<?php echo e(route('guide')); ?>" class="underline">инструкцию</a>.
        </div>
    <?php endif; ?>
</section>

<section>
    <h2 class="section-title">Последние бэкапы</h2>
    <?php $__empty_1 = true; $__currentLoopData = $recentRuns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $run): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <a href="<?php echo e(route('backup-runs.show', $run)); ?>" class="card card-hover block p-4 mb-2 no-underline text-inherit">
            <div class="flex justify-between gap-3 text-sm">
                <span>
                    <?php echo e($run->server->name); ?>

                </span>
                <span class="text-slate-400"><?php echo e($run->status); ?> · <?php echo e($run->created_at?->diffForHumans()); ?></span>
            </div>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="text-sm text-slate-400">Бэкапов ещё не было</p>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/dashboard.blade.php ENDPATH**/ ?>