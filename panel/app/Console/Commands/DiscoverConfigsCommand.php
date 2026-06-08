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

        $server->markDiscoveryRunning();

        try {
            $pid = $discovery->startRemote($server);
            $this->info("Remote discovery started (PID {$pid})");

            while ($server->fresh()->isDiscoveryRunning()) {
                sleep(3);
                $discovery->advanceDiscovery($server->fresh());
            }

            $server->refresh();
            $this->info("Status: {$server->config_discovery_status}");
            if ($server->config_discovery_error) {
                $this->error($server->config_discovery_error);
            }

            return $server->config_discovery_status === Server::DISCOVERY_COMPLETED
                ? self::SUCCESS
                : self::FAILURE;
        } catch (\Throwable $e) {
            $server->markDiscoveryFailed($e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
