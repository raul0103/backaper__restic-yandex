<?php ($parsed = $parsed ?? $run->parsedLog()); ?>
<?php ($cloud = $parsed['cloud']); ?>
<?php ($artifacts = $parsed['artifacts']); ?>
<?php ($restic = $parsed['restic']); ?>
<?php ($hasCloud = $cloud['total'] || $cloud['free'] || $cloud['used']); ?>
<?php ($hasSizes = $hasCloud || $artifacts !== [] || $restic); ?>

<?php if($parsed['insufficient_storage'] ?? false): ?>
    <div class="mb-4 rounded-xl border px-4 py-3 text-sm alert-error">
        <strong>Недостаточно места на Яндекс.Диске</strong> (ошибка 507).
        Освободите место в облаке или смените аккаунт — это не нехватка RAM на сервере.
        <?php if($cloud['free']): ?>
            Сейчас свободно: <?php echo e($cloud['free']); ?> из <?php echo e($cloud['total'] ?? '?'); ?>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if($hasSizes): ?>
    <div id="run-sizes" class="card p-4 sm:p-6 mb-4">
        <h2 class="section-title !text-base !mb-3">Размеры и облако</h2>

        <?php if($hasCloud): ?>
            <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 text-sm">
                <?php if($cloud['total']): ?>
                    <div><dt class="text-slate-400">Всего на Диске</dt><dd class="font-medium"><?php echo e($cloud['total']); ?></dd></div>
                <?php endif; ?>
                <?php if($cloud['used']): ?>
                    <div><dt class="text-slate-400">Занято</dt><dd class="font-medium"><?php echo e($cloud['used']); ?></dd></div>
                <?php endif; ?>
                <?php if($cloud['free']): ?>
                    <div><dt class="text-slate-400">Свободно</dt><dd class="font-medium <?php echo e($parsed['insufficient_storage'] ? 'text-red-700' : 'text-brand-700'); ?>"><?php echo e($cloud['free']); ?></dd></div>
                <?php endif; ?>
                <?php if($cloud['trashed']): ?>
                    <div><dt class="text-slate-400">В корзине</dt><dd class="font-medium"><?php echo e($cloud['trashed']); ?></dd></div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <?php if($artifacts !== [] || $restic): ?>
            <div class="table-wrap">
                <table class="data-table text-sm">
                    <thead>
                        <tr>
                            <th>Артефакт</th>
                            <th>Размер</th>
                            <th>В облаке</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $artifacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td>
                                    <?php if($item['type'] === 'db'): ?>
                                        Дамп БД <code class="text-xs"><?php echo e($item['name']); ?></code>
                                    <?php else: ?>
                                        Tar проекта <code class="text-xs"><?php echo e($item['name']); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono"><?php echo e($item['human']); ?></td>
                                <td>
                                    <?php if($item['uploaded']): ?>
                                        <span class="badge badge-success">да</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">нет</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php if($restic): ?>
                            <tr>
                                <td>Restic (файлы сайта)</td>
                                <td class="font-mono">
                                    <?php echo e($restic['stored'] ?? $restic['added'] ?? '—'); ?>

                                    <?php if($restic['added'] && $restic['stored']): ?>
                                        <span class="text-slate-400 text-xs block">исходно <?php echo e($restic['added']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($restic['files']): ?>
                                        <span class="text-slate-500 text-xs"><?php echo e(number_format($restic['files'])); ?> файлов</span>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\rshak\Desktop\GIT\backaper__restic-yandex\panel-v2\resources\views/backup-runs/_sizes.blade.php ENDPATH**/ ?>