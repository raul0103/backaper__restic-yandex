<?php
    $steps = [
        1 => ['label' => 'Подключение', 'route' => 'servers.wizard.connect'],
        2 => ['label' => 'Установка', 'route' => 'servers.wizard.install'],
        3 => ['label' => 'Базы данных', 'route' => 'servers.wizard.content'],
    ];
?>
<div class="flex flex-wrap gap-2 mb-8">
    <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $done = $server->setup_step > $num || ($num === 2 && $server->is_setup_complete);
            $active = $current === $num;
            $class = $active ? 'step-pill-active' : ($done ? 'step-pill-done' : 'step-pill');
        ?>
        <?php if($done || $active || $num <= $server->setup_step): ?>
            <a href="<?php echo e(route($step['route'], $server)); ?>" class="step-pill <?php echo e($class); ?>">
                <span><?php echo e($num); ?></span> <?php echo e($step['label']); ?>

            </a>
        <?php else: ?>
            <span class="step-pill"><?php echo e($num); ?> <?php echo e($step['label']); ?></span>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/servers/wizard/_steps.blade.php ENDPATH**/ ?>