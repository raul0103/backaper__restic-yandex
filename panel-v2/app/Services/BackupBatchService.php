<?php

namespace App\Services;

use App\Models\BackupBatch;
use App\Models\BackupBatchItem;
use App\Models\Server;
use RuntimeException;
use Throwable;

class BackupBatchService
{
    public function __construct(private BackupOrchestrator $orchestrator) {}

    /**
     * @param  list<int>  $serverIds
     */
    public function create(array $serverIds, string $mode, int $pollSeconds = 900): BackupBatch
    {
        if ($serverIds === []) {
            throw new RuntimeException('Выберите хотя бы один сервер.');
        }
        if (! in_array($mode, [BackupBatch::MODE_FILES, BackupBatch::MODE_DATABASES, BackupBatch::MODE_BOTH], true)) {
            throw new RuntimeException('Неверный режим бэкапа.');
        }

        $busy = array_intersect($serverIds, Server::activeQueueServerIds());
        if ($busy !== []) {
            $names = Server::query()
                ->whereIn('id', $busy)
                ->pluck('name')
                ->implode(', ');
            throw new RuntimeException('Уже в очереди: '.$names);
        }

        $pollSeconds = max(60, $pollSeconds);

        $blocking = BackupBatch::query()
            ->whereIn('status', ['pending', 'running'])
            ->orderBy('id')
            ->first();

        $batch = BackupBatch::create([
            'status' => 'pending',
            'mode' => $mode,
            'poll_seconds' => $pollSeconds,
            'message' => $blocking
                ? "Ожидает завершения очереди #{$blocking->id}"
                : 'Ожидает запуска',
        ]);

        foreach (array_values($serverIds) as $i => $serverId) {
            BackupBatchItem::create([
                'backup_batch_id' => $batch->id,
                'server_id' => $serverId,
                'position' => $i,
                'status' => 'pending',
            ]);
        }

        return $batch->fresh(['items.server']);
    }

    /**
     * Глобально одна очередь за раз: если никто не running — стартуем самую старую pending.
     */
    public function tryStartNext(): ?BackupBatch
    {
        if (BackupBatch::query()->where('status', 'running')->exists()) {
            return null;
        }

        $next = BackupBatch::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        if (! $next) {
            return null;
        }

        $this->spawnProcessor((int) $next->id);

        return $next;
    }

    public function spawnProcessor(int $batchId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $id = (int) $batchId;

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' backaper:process-batch '.$id;
            pclose(popen($cmd, 'r'));

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan).' backaper:process-batch '.$id
            .' >> '.escapeshellarg(storage_path('logs/batch-'.$id.'.log')).' 2>&1 &';
        exec($cmd);
    }

    /** Последовательный прогон: один сервер → опрос раз в poll_seconds → следующий. */
    public function process(BackupBatch $batch): BackupBatch
    {
        $batch->refresh();
        if ($batch->status === 'cancelled') {
            $this->tryStartNext();

            return $batch;
        }

        if (in_array($batch->status, ['completed', 'failed'], true)) {
            $this->tryStartNext();

            return $batch;
        }

        // Не запускаем параллельно другую очередь
        $otherRunning = BackupBatch::query()
            ->where('status', 'running')
            ->where('id', '!=', $batch->id)
            ->orderBy('id')
            ->first();
        if ($otherRunning) {
            $batch->update([
                'status' => 'pending',
                'message' => "Ожидает завершения очереди #{$otherRunning->id}",
            ]);

            return $batch->fresh(['items.server', 'items.backupRun']);
        }

        $batch->update([
            'status' => 'running',
            'started_at' => $batch->started_at ?? now(),
            'message' => 'Очередь запущена',
        ]);

        $options = $batch->modeOptions();
        $poll = max(60, (int) $batch->poll_seconds);
        $hadFailure = false;

        foreach ($batch->items()->orderBy('position')->get() as $item) {
            $batch->refresh();
            if ($batch->status === 'cancelled') {
                $this->skipRemaining($batch, 'Отменено');
                $this->tryStartNext();

                return $batch->fresh(['items.server', 'items.backupRun']);
            }

            if ($item->isTerminal()) {
                continue;
            }

            $batch->update([
                'current_item_id' => $item->id,
                'message' => "Сервер: {$item->server->name}",
            ]);

            try {
                $this->runItem($batch, $item, $options, $poll);
            } catch (Throwable $e) {
                $hadFailure = true;
                $item->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }

            $item->refresh();
            if ($item->status === 'failed') {
                $hadFailure = true;
            }
        }

        $batch->update([
            'status' => $hadFailure ? 'failed' : 'completed',
            'current_item_id' => null,
            'finished_at' => now(),
            'message' => $hadFailure
                ? 'Очередь завершена с ошибками'
                : 'Все серверы обработаны',
        ]);

        // Следующая ожидающая очередь
        $this->tryStartNext();

        return $batch->fresh(['items.server', 'items.backupRun']);
    }

    /**
     * @param  array{files: bool, databases: bool}  $options
     */
    private function runItem(BackupBatch $batch, BackupBatchItem $item, array $options, int $pollSeconds): void
    {
        $item->update([
            'status' => 'running',
            'started_at' => now(),
            'message' => 'Запуск по SSH…',
        ]);

        $run = $this->orchestrator->startServerBackup($item->server, $options);
        $item->update([
            'backup_run_id' => $run->id,
            'message' => $run->isRunning()
                ? 'Бэкап на сервере (ожидание, опрос раз в '.($pollSeconds / 60).' мин)'
                : ('Старт: '.$run->status),
        ]);

        if (! $run->isRunning()) {
            $item->update([
                'status' => $run->status === 'completed' ? 'completed' : 'failed',
                'message' => $run->status === 'completed' ? 'Готово' : ($run->log ?: 'Не удалось запустить'),
                'finished_at' => now(),
            ]);

            return;
        }

        while ($run->fresh()->isRunning()) {
            $batch->refresh();
            if ($batch->status === 'cancelled') {
                $item->update([
                    'status' => 'skipped',
                    'message' => 'Отменено (бэкап на сервере мог продолжаться)',
                    'finished_at' => now(),
                ]);

                return;
            }

            $this->orchestrator->advanceBackup($run->fresh());
            $run->refresh();

            if (! $run->isRunning()) {
                break;
            }

            $item->update([
                'message' => 'Ещё работает… следующая SSH-проверка через '.($pollSeconds / 60).' мин',
            ]);

            // Не блокируем на весь poll_seconds: UI/другой процесс мог уже
            // пометить run completed — проверяем чаще короткими sleep.
            $deadline = time() + $pollSeconds;
            while (time() < $deadline) {
                if (! $run->fresh()->isRunning()) {
                    break 2;
                }
                $batch->refresh();
                if ($batch->status === 'cancelled') {
                    $item->update([
                        'status' => 'skipped',
                        'message' => 'Отменено (бэкап на сервере мог продолжаться)',
                        'finished_at' => now(),
                    ]);

                    return;
                }
                sleep(min(30, max(1, $deadline - time())));
            }
        }

        $run->refresh();
        $item->update([
            'status' => $run->status === 'completed' ? 'completed' : 'failed',
            'message' => $run->status === 'completed' ? 'Готово' : 'Бэкап завершился с ошибкой',
            'finished_at' => now(),
        ]);
    }

    private function skipRemaining(BackupBatch $batch, string $reason): void
    {
        $batch->items()
            ->where('status', 'pending')
            ->update([
                'status' => 'skipped',
                'message' => $reason,
                'finished_at' => now(),
            ]);

        $batch->update([
            'status' => 'cancelled',
            'current_item_id' => null,
            'finished_at' => now(),
            'message' => $reason,
        ]);
    }

    public function cancel(BackupBatch $batch): void
    {
        if (! $batch->isActive()) {
            return;
        }

        $wasPending = $batch->status === 'pending';

        $batch->loadMissing('items.backupRun');

        foreach ($batch->items as $item) {
            $run = $item->backupRun;
            if ($run && $run->isRunning()) {
                $note = "\n[panel] очередь закрыта вручную";
                $run->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'log' => ($run->log ?? '').$note,
                ]);
            }
        }

        // Сразу закрываем очередь: не ждём SSH-опроса и мёртвого process-batch.
        $batch->items()
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status' => 'skipped',
                'message' => $wasPending ? 'Отменено' : 'Очередь закрыта',
                'finished_at' => now(),
            ]);

        $batch->update([
            'status' => 'cancelled',
            'message' => $wasPending
                ? 'Отменено до запуска'
                : 'Очередь закрыта. Бэкап на сервере мог продолжаться — смотрите лог.',
            'finished_at' => now(),
            'current_item_id' => null,
        ]);

        $this->tryStartNext();
    }
}
