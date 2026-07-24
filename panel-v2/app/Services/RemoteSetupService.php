<?php

namespace App\Services;

use App\Models\Server;

class RemoteSetupService
{
    public function __construct(private SshService $ssh) {}

    public function setup(Server $server): string
    {
        if (! $server->readyForRemoteSetup()) {
            throw new \RuntimeException('Укажите SSH, rclone token и сохраните шаг 1 — затем установите restic.');
        }

        $this->ssh->exec($server, 'mkdir -p ~/backaper/scripts ~/backaper/logs ~/backaper/tmp ~/bin');
        $this->uploadScripts($server);

        if (! empty(trim((string) $server->rclone_token))) {
            $this->ssh->upload($server, '~/backaper/rclone-token.json', trim($server->rclone_token));
        }

        // Закрепляем slug из названия сервера (не из hostname машины)
        if ($server->restic_repo_slug === null || $server->restic_repo_slug === '') {
            $fromName = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) $server->name) ?: 'server';
            $server->update(['restic_repo_slug' => $fromName]);
        }

        $repoSlug = $server->fresh()->repoSlug();
        $env = $this->buildSetupEnv($server, $repoSlug);
        $command = "env {$env} bash ~/backaper/scripts/install.sh 2>&1";
        $log = $this->ssh->exec($server, $command);

        $success = str_contains($log, 'SETUP_COMPLETE');

        $server->update([
            'is_setup_complete' => $success,
            'setup_log' => $success ? null : $log,
        ]);

        return $log;
    }

    private function uploadScripts(Server $server): void
    {
        $names = [
            'install.sh',
            'backup.sh',
            'backup-files.sh',
            'backup-databases.sh',
            'parse-db-config.php',
            'test-full-home-backup.sh',
        ];
        $chmod = [];
        foreach ($names as $name) {
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
            $this->ssh->exec($server, 'chmod +x '.implode(' ', $chmod));
        }
    }

    /** @return list<string> */
    public function setupAll(): array
    {
        $logs = [];
        $servers = \App\Models\Server::query()->get();

        foreach ($servers as $server) {
            if (! $server->readyForRemoteSetup()) {
                continue;
            }
            try {
                $logs[$server->name] = $this->setup($server);
            } catch (\Throwable $e) {
                $logs[$server->name] = 'ERROR: '.$e->getMessage();
            }
        }

        return $logs;
    }

    private function buildSetupEnv(Server $server, string $repoSlug): string
    {
        $pairs = [
            'BACKAPER_RCLONE_REMOTE' => $server->rclone_remote,
            'BACKAPER_RESTIC_PASSWORD' => $server->restic_password,
            'BACKAPER_RESTIC_REPOSITORY' => $server->resticRepository(),
            'BACKAPER_CLOUD_PREFIX' => $server->cloudPrefix(),
        ];

        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.'='.escapeshellarg($value);
        }

        return implode(' ', $parts);
    }
}
