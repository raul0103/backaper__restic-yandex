<?php

namespace App\Services;

use App\Models\BackupPath;
use App\Models\BackupRun;
use App\Models\DatabaseCredential;
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
        $server->syncFullBackupPath();

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

    /** @return BackupRun */
    public function waitForBackup(BackupRun $run, int $pollSeconds = 5): BackupRun
    {
        while ($run->fresh()->isRunning()) {
            sleep($pollSeconds);
            $this->advanceBackup($run->fresh());
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

    private function assertReady(Server $server): void
    {
        if (! $server->readyForRemoteSetup()) {
            throw new RuntimeException('Сначала укажите SSH и токен Яндекс.Диска.');
        }
        if (! $server->is_setup_complete) {
            throw new RuntimeException('Сначала установите restic на сервере (шаг 2).');
        }
        if (! $server->readyForBackup()) {
            throw new RuntimeException('Добавьте хотя бы один путь или базу для бэкапа.');
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
set +e
bash ~/backaper/scripts/backup.sh
ec=\$?
echo "\$ec" > "{$donePath}"
exit "\$ec"
BASH;

        $this->ssh->upload($server, $runScriptPath, $runScript);

        $start = <<<BASH
base={$base}
mkdir -p "\$base"
rm -f "{$donePath}" "{$pidPath}" "{$logPath}"
chmod +x "{$runScriptPath}"
if command -v stdbuf >/dev/null 2>&1; then
  setsid stdbuf -oL -eL bash "{$runScriptPath}" > "{$logPath}" 2>&1 < /dev/null &
else
  setsid bash "{$runScriptPath}" > "{$logPath}" 2>&1 < /dev/null &
fi
echo \$! > "{$pidPath}"
sleep 1
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
        $logPath = $base.'/run.log';
        $marker = 'backaper-backup-'.$run->id;

        $check = <<<BASH
if [ -f "{$donePath}" ]; then
  ec=\$(tr -d '[:space:]' < "{$donePath}")
  if [ "\$ec" = "0" ] || [ "\$ec" = "done" ]; then
    echo DONE
  else
    echo FAILED
  fi
  exit 0
fi
if [ -f "{$pidPath}" ]; then
  pid=\$(tr -d '[:space:]' < "{$pidPath}")
  if [ -n "\$pid" ] && kill -0 "\$pid" 2>/dev/null; then
    echo RUNNING
    exit 0
  fi
fi
if pgrep -f "{$marker}" >/dev/null 2>&1; then
  echo RUNNING
  exit 0
fi
if pgrep -x restic >/dev/null 2>&1 || pgrep -x rclone >/dev/null 2>&1; then
  if [ -f "{$logPath}" ]; then
    age=\$(( \$(date +%s) - \$(stat -c %Y "{$logPath}" 2>/dev/null || echo 0) ))
    if [ "\$age" -lt 600 ]; then
      echo RUNNING
      exit 0
    fi
  fi
fi
if [ -f "{$logPath}" ]; then
  age=\$(( \$(date +%s) - \$(stat -c %Y "{$logPath}" 2>/dev/null || echo 0) ))
  if [ "\$age" -lt 180 ]; then
    echo RUNNING
    exit 0
  fi
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
        }
    }

    private function readRemoteLog(BackupRun $run, Server $server): string
    {
        try {
            $log = trim($this->ssh->read($server, $this->remoteBaseDir($run, $server).'/run.log'));

            return $this->toUtf8($log) ?? '';
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

    /** @return array<string, mixed> */
    private function buildManifest(Server $server): array
    {
        $paths = $server->backupPaths()
            ->where('is_enabled', true)
            ->get()
            ->map(fn (BackupPath $p) => [
                'path' => $p->path,
                'label' => $p->displayName(),
                'slug' => $server->storageSlug($p->path === '~' || $p->path === '/'
                    ? ($p->path === '/' ? 'root' : 'home')
                    : ($p->label ?: basename(rtrim($p->path, '/')) ?: 'files')),
            ])
            ->values()
            ->all();

        $databases = $server->databases()
            ->where('is_enabled', true)
            ->get()
            ->map(fn (DatabaseCredential $d) => [
                'label' => $d->displayName(),
                'host' => $d->database_server ?: 'localhost',
                'name' => $d->database_name,
                'user' => $d->database_user,
                'password' => $d->database_password,
            ])
            ->values()
            ->all();

        return [
            'restic_repository' => $server->resticRepository(),
            'restic_password' => $server->restic_password,
            'rclone_remote' => $server->rclone_remote,
            'cloud_prefix' => $server->cloudPrefix(),
            'exclusions' => $server->fileExclusions(),
            'paths' => $paths,
            'databases' => $databases,
        ];
    }
}
