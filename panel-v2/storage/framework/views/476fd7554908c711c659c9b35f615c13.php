<?php $__env->startSection('title', 'Шаг 2 — '.$server->name); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('servers.wizard._steps', ['current' => 2, 'server' => $server], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="mb-8">
    <h1 class="page-title">Шаг 2 — Установка</h1>
    <p class="page-subtitle">Поставим на сервер программы для шифрования и загрузки на Яндекс.Диск.</p>
</div>

<div class="help-box mb-6">
    <strong>Что произойдёт:</strong> по SSH установятся restic и rclone, создастся папка бэкапов на Яндекс.Диске.
    На сервере ничего вручную настраивать не нужно.
</div>

<div class="card p-6 space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-slate-600">Статус:</span>
        <?php if($server->is_setup_complete): ?>
            <span class="badge badge-success">Установлено</span>
        <?php else: ?>
            <span class="badge badge-warning">Ещё не установлено</span>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo e(route('servers.wizard.install.run', $server)); ?>" class="flex flex-wrap gap-3">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-primary">
            <?php echo e($server->is_setup_complete ? 'Переустановить' : 'Установить на сервер'); ?>

        </button>
        <a href="<?php echo e(route('servers.wizard.connect', $server)); ?>" class="btn btn-secondary">← Назад</a>
        <?php if($server->is_setup_complete): ?>
            <a href="<?php echo e(route('servers.wizard.content', $server)); ?>" class="btn btn-secondary">Далее →</a>
        <?php endif; ?>
    </form>

    <?php if($server->setup_log): ?>
        <details class="mt-2">
            <summary class="text-sm text-slate-500 cursor-pointer">Лог ошибки</summary>
            <pre class="log-block mt-2 text-xs"><?php echo e($server->setup_log); ?></pre>
        </details>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/wizard/install.blade.php ENDPATH**/ ?>