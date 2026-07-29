<?php

namespace App\Console\Commands;

use App\Models\BackupBatch;
use App\Services\BackupBatchService;
use Illuminate\Console\Command;

class ProcessBackupBatchCommand extends Command
{
    protected $signature = 'backaper:process-batch {batch : ID очереди}';

    protected $description = 'Последовательно обработать очередь бэкапов (один сервер за раз)';

    public function handle(BackupBatchService $batches): int
    {
        $batch = BackupBatch::find($this->argument('batch'));
        if (! $batch) {
            $this->error('Очередь не найдена');

            return self::FAILURE;
        }

        if (in_array($batch->status, ['completed', 'cancelled'], true)) {
            $this->info("Уже завершена: {$batch->status}");

            return self::SUCCESS;
        }

        set_time_limit(0);
        $this->info("Очередь #{$batch->id}: режим {$batch->mode}, опрос каждые {$batch->poll_seconds} с");

        $batch = $batches->process($batch);
        $this->info("Итог: {$batch->status} — {$batch->message}");

        return $batch->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
