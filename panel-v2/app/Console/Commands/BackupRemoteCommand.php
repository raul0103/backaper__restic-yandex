<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\BackupOrchestrator;
use Illuminate\Console\Command;

class BackupRemoteCommand extends Command
{
    protected $signature = 'backaper:backup {server? : ID сервера} {--all : Бэкап всех готовых серверов} {--files : Только файлы} {--databases : Только дампы БД}';

    protected $description = 'По SSH: restic (файлы) и/или rclone дампы БД';

    public function handle(BackupOrchestrator $orchestrator): int
    {
        $options = $this->modeOptions();

        if ($this->option('all')) {
            $servers = Server::query()->get()->filter(fn (Server $s) => $s->readyForBackup());
            foreach ($servers as $server) {
                $this->info("Backup: {$server->name}");
                $run = $orchestrator->startServerBackup($server, $options);
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

        $run = $orchestrator->startServerBackup($server, $options);
        if ($run->isRunning()) {
            $this->info('Backup started on server, waiting…');
            $run = $orchestrator->waitForBackup($run);
        }
        $this->line($run->log);
        $this->info("Status: {$run->status}");

        return $run->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{files: bool, databases: bool} */
    private function modeOptions(): array
    {
        $filesOnly = (bool) $this->option('files');
        $dbsOnly = (bool) $this->option('databases');
        if ($filesOnly && ! $dbsOnly) {
            return ['files' => true, 'databases' => false];
        }
        if ($dbsOnly && ! $filesOnly) {
            return ['files' => false, 'databases' => true];
        }

        return ['files' => true, 'databases' => true];
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
