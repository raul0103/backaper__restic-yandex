<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\Project;
use App\Models\Server;
use RuntimeException;

class BackupOrchestrator
{
    /** @var array<int, string> */
    private array $remoteBaseCache = [];

    public function __construct(private SshService $ssh) {}

    public function startServerBackup(Server $server): BackupRun
    {
        $this->assertReady($server);

        $run = BackupRun::create([
            'server_id' => $server->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $pid = $this->startRemote($run, $server, $this->buildManifest($server));
            $run->update(['remote_pid' => $pid]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'log' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $run->fresh();
    }

    public function startProjectBackup(Project $project): BackupRun
    {
        $project->load('database');
        $server = $project->server;

        $this->assertReady($server);

        $run = BackupRun::create([
            'server_id' => $server->id,
            'project_id' => $project->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $pid = $this->startRemote($run, $server, $this->buildManifest($server, collect([$project])));
            $run->update(['remote_pid' => $pid]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'log' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $run->fresh();
    }

    public function advanceBackup(BackupRun $run): void
    {
        if (! $run->isRunning()) {
            return;
        }

        try {
            $state = $this->pollRemote($run, $run->server);

            if ($state === 'running') {
                $this->syncRunningLog($run, $run->server);

                return;
            }

            $log = $this->readRemoteLog($run, $run->server);

            if ($state === 'failed') {
                $run->update([
                    'status' => 'failed',
                    'log' => $log !== '' ? $log : 'Бэкап на сервере завершился с ошибкой',
                    'finished_at' => now(),
                    'remote_pid' => null,
                ]);

                return;
            }

            $run->update([
                'status' => str_contains($log, 'BACKUP_COMPLETE') ? 'completed' : 'failed',
                'log' => $log,
                'finished_at' => now(),
                'remote_pid' => null,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'log' => trim(($run->log ?? '')."\n".$e->getMessage()),
                'finished_at' => now(),
                'remote_pid' => null,
            ]);
        }
    }

    /** @return BackupRun */
    public function waitForBackup(BackupRun $run, int $pollSeconds = 5): BackupRun
    {
        while ($run->fresh()->isRunning()) {
            sleep($pollSeconds);
            $this->advanceBackup($run->fresh());
        }

        return $run->fresh();
    }

    private function assertReady(Server $server): void
    {
        if (! $server->readyForRemoteSetup()) {
            throw new RuntimeException('Сначала завершите мастер и укажите restic/rclone.');
        }
        if (! $server->is_setup_complete) {
            throw new RuntimeException('Сначала выполните установку restic/rclone на сервере.');
        }
        if (! $server->readyForBackup()) {
            throw new RuntimeException('Нет готовых проектов (конфиг, БД, путь).');
        }
    }

    private function remoteBaseDir(BackupRun $run, Server $server): string
    {
        if (isset($this->remoteBaseCache[$run->id])) {
            return $this->remoteBaseCache[$run->id];
        }

        $home = rtrim($this->ssh->homeDir($server), '/');
        $this->remoteBaseCache[$run->id] = $home.'/.backaper/backup-'.$run->id;

        return $this->remoteBaseCache[$run->id];
    }

    /** @param array<string, mixed> $manifest */
    private function startRemote(BackupRun $run, Server $server, array $manifest): int
    {
        $base = $this->remoteBaseDir($run, $server);
        $manifestPath = $base.'/manifest.json';
        $runScriptPath = $base.'/run.sh';
        $pidPath = $base.'/pid';
        $donePath = $base.'/done';
        $logPath = $base.'/run.log';

        $backupScript = file_get_contents(resource_path('scripts/remote/backup.sh'));
        $this->ssh->exec($server, 'mkdir -p ~/backaper/scripts', 15);
        $this->ssh->upload($server, '~/backaper/scripts/backup.sh', $backupScript);
        $this->ssh->exec($server, 'chmod +x ~/backaper/scripts/backup.sh', 15);

        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($manifestJson === false) {
            throw new RuntimeException('Не удалось подготовить манифест бэкапа');
        }

        $this->ssh->upload($server, $manifestPath, $manifestJson);

        $marker = 'backaper-backup-'.$run->id;
        $runScript = <<<BASH
#!/bin/bash
# {$marker}
export BACKAPER_MANIFEST="{$manifestPath}"
bash ~/backaper/scripts/backup.sh
echo done > "{$donePath}"
BASH;

        $this->ssh->upload($server, $runScriptPath, $runScript);

        $start = <<<BASH
base={$base}
mkdir -p "\$base"
rm -f "{$donePath}" "{$pidPath}" "{$logPath}"
chmod +x "{$runScriptPath}"
nohup bash "{$runScriptPath}" > "{$logPath}" 2>&1 &
echo \$! > "{$pidPath}"
cat "{$pidPath}"
BASH;

        $output = trim($this->ssh->exec($server, $start, 30));
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        $pid = (int) ($lines[array_key_last($lines)] ?? 0);

        if ($pid <= 0) {
            $log = '';
            try {
                $log = trim($this->ssh->read($server, $logPath));
            } catch (\Throwable) {
                // ignore
            }

            throw new RuntimeException(
                'Не удалось запустить бэкап на сервере'
                .($output !== '' ? ': '.$output : '')
                .($log !== '' ? ' | log: '.$log : ''),
            );
        }

        return $pid;
    }

    /** @return 'running'|'done'|'failed' */
    private function pollRemote(BackupRun $run, Server $server): string
    {
        $base = $this->remoteBaseDir($run, $server);
        $pidPath = $base.'/pid';
        $donePath = $base.'/done';

        $check = <<<BASH
if [ -f "{$donePath}" ]; then
  echo DONE
  exit 0
fi
if [ -f "{$pidPath}" ] && kill -0 "\$(cat "{$pidPath}")" 2>/dev/null; then
  echo RUNNING
  exit 0
fi
echo FAILED
BASH;

        $state = trim($this->ssh->exec($server, $check, 30));

        return match ($state) {
            'RUNNING' => 'running',
            'DONE' => 'done',
            default => 'failed',
        };
    }

    private function syncRunningLog(BackupRun $run, Server $server): void
    {
        try {
            $log = $this->readRemoteLog($run, $server);
            if ($log !== '' && $log !== ($run->log ?? '')) {
                $run->update(['log' => $log]);
            }
        } catch (\Throwable) {
            // ignore transient read errors while backup runs
        }
    }

    private function readRemoteLog(BackupRun $run, Server $server): string
    {
        try {
            $log = trim($this->ssh->read($server, $this->remoteBaseDir($run, $server).'/run.log'));

            return $this->toUtf8($log);
        } catch (\Throwable) {
            return '';
        }
    }

    private function toUtf8(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1251');

        return $converted !== false ? $converted : mb_scrub($value, 'UTF-8');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Project>|null  $only
     * @return array<string, mixed>
     */
    private function buildManifest(Server $server, $only = null): array
    {
        $projects = ($only ?? $server->projects()->with('database')->where('is_enabled', true)->get())
            ->map(function (Project $p) use ($server) {
                $db = $p->database;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $server->storageSlug($p->name),
                    'root_path' => $p->root_path,
                    'session_table' => $p->sessionTable(),
                    'database' => [
                        'host' => $db?->database_server ?: 'localhost',
                        'name' => $db?->database_name,
                        'user' => $db?->database_user,
                        'password' => $db?->database_password,
                    ],
                    'exclusions' => $p->effectiveExclusions(),
                ];
            })
            ->values()
            ->all();

        return [
            'restic_repository' => $server->resticRepository(),
            'restic_password' => $server->restic_password,
            'rclone_remote' => $server->rclone_remote,
            'cloud_prefix' => $server->cloudPrefix(),
            'projects' => $projects,
        ];
    }
}
