<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\BackupOrchestrator;
use Illuminate\Console\Command;

class BackupRemoteCommand extends Command
{
    protected $signature = 'backaper:backup {server? : ID сервера} {--all : Бэкап всех готовых серверов}';

    protected $description = 'По SSH: restic snapshot + rclone дампы БД и архивы проектов';

    public function handle(BackupOrchestrator $orchestrator): int
    {
        if ($this->option('all')) {
            $servers = Server::query()->get()->filter(fn (Server $s) => $s->readyForBackup());
            foreach ($servers as $server) {
                $this->info("Backup: {$server->name}");
                $run = $orchestrator->startServerBackup($server);
                if ($run->isRunning()) {
                    $run = $orchestrator->waitForBackup($run);
                }
                $this->line("  status: {$run->status}");
            }

            return self::SUCCESS;
        }

        $server = $this->resolveServer();
        if (! $server) {
            return self::FAILURE;
        }

        $run = $orchestrator->startServerBackup($server);
        if ($run->isRunning()) {
            $this->info('Backup started on server, waiting…');
            $run = $orchestrator->waitForBackup($run);
        }
        $this->line($run->log);
        $this->info("Status: {$run->status}");

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    private function resolveServer(): ?Server
    {
        $id = $this->argument('server');
        if (! $id) {
            $this->error('Укажите server ID или --all');

            return null;
        }

        return Server::find($id);
    }
}
