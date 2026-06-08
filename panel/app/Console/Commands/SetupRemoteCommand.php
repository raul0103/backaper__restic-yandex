<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\BackupOrchestrator;
use App\Services\RemoteSetupService;
use Illuminate\Console\Command;

class SetupRemoteCommand extends Command
{
    protected $signature = 'backaper:setup {server? : ID сервера} {--all : Настроить все готовые серверы}';

    protected $description = 'По SSH: установить restic + rclone на удалённом сервере';

    public function handle(RemoteSetupService $setup): int
    {
        if ($this->option('all')) {
            $logs = $setup->setupAll();
            foreach ($logs as $name => $log) {
                $this->line("=== {$name} ===");
                $this->line($log);
            }

            return self::SUCCESS;
        }

        $server = $this->resolveServer();
        if (! $server) {
            return self::FAILURE;
        }

        $this->info("Setup: {$server->name} ({$server->ssh_user}@{$server->host})");
        $log = $setup->setup($server);
        $this->line($log);

        return $server->fresh()->is_setup_complete ? self::SUCCESS : self::FAILURE;
    }

    private function resolveServer(): ?Server
    {
        $id = $this->argument('server');
        if (! $id) {
            $this->error('Укажите server ID или --all');

            return null;
        }

        $server = Server::find($id);
        if (! $server) {
            $this->error("Сервер #{$id} не найден");

            return null;
        }

        return $server;
    }
}
