<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ConfigDiscoveryService;
use Illuminate\Console\Command;

class DiscoverConfigsCommand extends Command
{
    protected $signature = 'backaper:discover-configs {server : ID сервера}';

    protected $description = 'Поиск MODX config.inc.php на сервере по SSH';

    public function handle(ConfigDiscoveryService $discovery): int
    {
        set_time_limit(0);

        $server = Server::find($this->argument('server'));

        if (! $server) {
            $this->error('Server not found');

            return self::FAILURE;
        }

        try {
            $result = $discovery->discoverSync($server);
            $this->info('Найдено конфигов: '.$result['found']);
            foreach ($result['paths'] as $path) {
                $this->line('  '.$path);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
