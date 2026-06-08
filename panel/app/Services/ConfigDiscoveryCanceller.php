<?php

namespace App\Services;

use App\Models\Server;

class ConfigDiscoveryCanceller
{
    public function __construct(
        private ConfigDiscoveryService $discovery,
    ) {}

    public function cancel(Server $server): bool
    {
        if (! $server->isDiscoveryRunning()) {
            return false;
        }

        $updated = Server::query()
            ->where('id', $server->id)
            ->where('config_discovery_status', Server::DISCOVERY_RUNNING)
            ->update([
                'config_discovery_status' => Server::DISCOVERY_FAILED,
                'config_discovery_error' => 'Поиск прерван пользователем',
                'config_discovery_pid' => null,
                'config_discovery_remote_pid' => null,
            ]);

        if ($updated === 0) {
            return false;
        }

        $this->discovery->stopRemote($server);

        return true;
    }
}
