<?php

namespace App\Console\Commands;

use App\Services\PasstoreSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncPasstoreServersCommand extends Command
{
    protected $signature = 'passtore:sync-ssh';

    protected $description = 'Импорт/обновление SSH-серверов из Passtore';

    public function handle(PasstoreSyncService $sync): int
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Passtore: всего {$result['total']}, новых {$result['created']}, обновлено {$result['updated']}");

        return self::SUCCESS;
    }
}
