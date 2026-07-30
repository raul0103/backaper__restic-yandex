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

    /**
     * @param  array{files?: bool, databases?: bool}  $options
     */
    public function startServerBackup(Server $server, array $options = []): BackupRun
    {
        $doFiles = $options['files'] ?? true;
        $doDatabases = $options['databases'] ?? true;
        if (! $doFiles && ! $doDatabases) {
            throw new RuntimeException('Укажите режим: файлы и/или базы.');
        }

        $this->assertReady($server, $doFiles, $doDatabases);
        if ($doFiles) {
            $server->syncFullBackupPath();
        }

        $run = BackupRun::create([
            'server_id' => $server->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $pid = $this->startRemote($run, $server, $this->buildManifest($server, $doFiles, $doDatabases));
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
            $this->advanceBackup($run->fresh());
            if (! $run->fresh()->isRunning()) {
                break;
            }
            sleep($pollSeconds);
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

            $log = $this->readRemoteLog($run, $run->server, full: true);

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
            if ($run->fresh()->status === 'completed') {
                $run->server->markBackupCompleted();
            }
        } catch (\Throwable $e) {
            // Обрыв SSH при опросе ≠ смерть бэкапа. CLI в screen живёт, панель не должна ставить failed.
            try {
                $this->syncRunningLog($run, $run->server);
            } catch (\Throwable) {
            }
            $note = '[panel] SSH poll error (бэкап на сервере мог продолжаться): '.$e->getMessage();
            $prev = (string) ($run->fresh()->log ?? '');
            if (! str_contains($prev, $note)) {
                $run->update(['log' => trim($prev."\n".$note)]);
            }
        }
    }

    /** Публичный peek лога для UI (даже если DB ещё пустая). */
    public function peekLog(BackupRun $run): string
    {
        try {
            return $this->readRemoteLog($run, $run->server, full: false);
        } catch (\Throwable) {
            return '';
        }
    }

    private function assertReady(Server $server, bool $doFiles = true, bool $doDatabases = true): void
    {
        if (! $server->readyForRemoteSetup()) {
            throw new RuntimeException('Сначала укажите SSH и токен Яндекс.Диска.');
        }
        if (! $server->is_setup_complete) {
            throw new RuntimeException('Сначала установите restic на сервере (шаг 2).');
        }
        if ($doFiles && ! $server->backupPaths()->where('is_enabled', true)->exists()) {
            $server->syncFullBackupPath();
        }
        if ($doFiles && ! $server->backupPaths()->where('is_enabled', true)->exists()) {
            throw new RuntimeException('Нет пути для бэкапа файлов.');
        }
        // Базы: либо из манифеста панели, либо discovery на сервере (backup-databases.sh).
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

    /** Залить CLI/panel скрипты на сервер. */
    private function uploadRemoteScripts(Server $server): void
    {
        $this->ssh->exec($server, 'mkdir -p ~/backaper/scripts', 15);
        $files = [
            'backup.sh',
            'backup-files.sh',
            'backup-databases.sh',
            'parse-db-config.php',
            'test-full-home-backup.sh',
        ];
        $chmod = [];
        foreach ($files as $name) {
            $local = resource_path('scripts/remote/'.$name);
            if (! is_file($local)) {
                continue;
            }
            $this->ssh->upload($server, '~/backaper/scripts/'.$name, file_get_contents($local));
            if (str_ends_with($name, '.sh')) {
                $chmod[] = '~/backaper/scripts/'.$name;
            }
        }
        if ($chmod !== []) {
            $this->ssh->exec($server, 'chmod +x '.implode(' ', $chmod), 15);
        }
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

        $this->uploadRemoteScripts($server);

        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($manifestJson === false) {
            throw new RuntimeException('Не удалось подготовить манифест бэкапа');
        }

        $this->ssh->upload($server, $manifestPath, $manifestJson);

        $marker = 'backaper-backup-'.$run->id;
        $session = 'bp'.$run->id;
        $runScript = <<<BASH
#!/bin/bash
# {$marker}
trap '' HUP
echo \$\$ > "{$pidPath}"
export BACKAPER_MANIFEST="{$manifestPath}"
export BACKAPER_RUN_LOG="{$logPath}"
printf '%s\n' "[panel] runner start pid=\$\$ log={$logPath}" >> "{$logPath}"
set +e
if command -v stdbuf >/dev/null 2>&1; then
  stdbuf -oL -eL bash ~/backaper/scripts/backup.sh
else
  bash ~/backaper/scripts/backup.sh
fi
ec=\$?
printf '%s\n' "[panel] runner exit=\$ec" >> "{$logPath}"
echo "\$ec" > "{$donePath}"
exit "\$ec"
BASH;

        $this->ssh->upload($server, $runScriptPath, $runScript);

        // screen -dm переживает обрыв SSH панели (как ручной CLI). Fallback: nohup+setsid.
        $start = <<<BASH
base={$base}
mkdir -p "\$base"
rm -f "{$donePath}" "{$pidPath}" "{$logPath}"
chmod +x "{$runScriptPath}"
printf '%s\n' "[panel] starting screen session {$session} at \$(date -Is)" > "{$logPath}"
session="{$session}"
if command -v screen >/dev/null 2>&1; then
  screen -S "\$session" -X quit >/dev/null 2>&1 || true
  screen -dmS "\$session" bash -c 'trap "" HUP; exec bash "{$runScriptPath}" >> "{$logPath}" 2>&1 < /dev/null'
  printf '%s\n' "[panel] screen -dmS \$session (check: screen -ls)" >> "{$logPath}"
else
  nohup bash -c 'trap "" HUP; setsid bash "{$runScriptPath}" >> "{$logPath}" 2>&1 < /dev/null &' >/dev/null 2>&1
  printf '%s\n' "[panel] started via nohup/setsid (no screen)" >> "{$logPath}"
fi
for i in 1 2 3 4 5 6 7 8 9 10; do
  [ -f "{$pidPath}" ] && break
  sleep 0.5
done
if [ -f "{$pidPath}" ]; then
  cat "{$pidPath}"
else
  pgrep -f "{$marker}" | head -1 || echo 0
fi
BASH;

        $output = trim($this->ssh->exec($server, $start, 60));
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
        $session = 'bp'.$run->id;

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
if command -v screen >/dev/null 2>&1 && screen -ls 2>/dev/null | grep -q "\.{$session}"; then
  echo RUNNING
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
if pgrep -x restic >/dev/null 2>&1 || pgrep -f 'rclone serve restic' >/dev/null 2>&1; then
  if [ -f "{$logPath}" ]; then
    age=\$(( \$(date +%s) - \$(stat -c %Y "{$logPath}" 2>/dev/null || echo 0) ))
    if [ "\$age" -lt 900 ]; then
      echo RUNNING
      exit 0
    fi
  fi
fi
if [ -f "{$logPath}" ]; then
  age=\$(( \$(date +%s) - \$(stat -c %Y "{$logPath}" 2>/dev/null || echo 0) ))
  if [ "\$age" -lt 300 ]; then
    echo RUNNING
    exit 0
  fi
fi
echo FAILED
BASH;

        $state = trim($this->ssh->exec($server, $check, 45));

        return match ($state) {
            'RUNNING' => 'running',
            'DONE' => 'done',
            default => 'failed',
        };
    }

    private function syncRunningLog(BackupRun $run, Server $server): void
    {
        try {
            $log = $this->readRemoteLog($run, $server, full: false);
            if ($log !== '' && $log !== ($run->log ?? '')) {
                $run->update(['log' => $log]);
            }
        } catch (\Throwable) {
        }
    }

    private function readRemoteLog(BackupRun $run, Server $server, bool $full = false): string
    {
        $logPath = $this->remoteBaseDir($run, $server).'/run.log';
        try {
            if ($full) {
                $log = trim($this->ssh->read($server, $logPath));
            } else {
                // Не escapeshellarg(): панель на Windows ломает кавычки для remote Linux
                $quoted = "'".str_replace("'", "'\\''", $logPath)."'";
                $log = trim($this->ssh->exec(
                    $server,
                    'tail -c 120000 '.$quoted.' 2>/dev/null || true',
                    30
                ));
            }

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

    /**
     * @return array<string, mixed>
     */
    private function buildManifest(Server $server, bool $doFiles = true, bool $doDatabases = true): array
    {
        $paths = [];
        if ($doFiles) {
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
        }

        $databases = [];
        if ($doDatabases) {
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
        }

        return [
            'restic_repository' => $server->resticRepository(),
            'restic_password' => $server->restic_password,
            'rclone_remote' => $server->rclone_remote,
            'cloud_prefix' => $server->cloudPrefix(),
            'exclusions' => $server->fileExclusions(),
            'backup_files' => $doFiles,
            'backup_databases' => $doDatabases,
            'paths' => $paths,
            'databases' => $databases,
        ];
    }
}
