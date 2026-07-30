<?php

namespace App\Services;

use App\Models\Server;

class PasstoreSyncService
{
    public function __construct(private PasstoreClient $client) {}

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function sync(): array
    {
        $items = $this->client->fetchSshAccesses();
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $server = Server::query()->where('host', $item['host'])->first();

            if ($server) {
                $server->fill([
                    'name' => $item['name'] ?: $server->name,
                    'passtore_name' => $item['name'],
                    'ssh_user' => $item['user'] ?: $server->ssh_user,
                    'ssh_port' => $item['port'] ?: $server->ssh_port,
                    'ssh_auth_type' => Server::AUTH_PASSWORD,
                    'ssh_password' => $item['password'] !== '' ? $item['password'] : $server->ssh_password,
                    'passtore_synced_at' => now(),
                ])->save();
                $updated++;

                continue;
            }

            $kind = $this->guessKind($item['host'], $item['user']);
            $slug = preg_replace('/[^a-zA-Z0-9._-]/', '-', $item['name']) ?: null;

            $server = Server::create([
                'name' => $item['name'] ?: $item['host'],
                'passtore_name' => $item['name'],
                'kind' => $kind,
                'host' => $item['host'],
                'ssh_port' => $item['port'] ?: 22,
                'ssh_user' => $item['user'] ?: 'root',
                'ssh_auth_type' => Server::AUTH_PASSWORD,
                'ssh_password' => $item['password'],
                'ssh_private_key' => '',
                'restic_password' => Server::DEFAULT_RESTIC_PASSWORD,
                'restic_repo_slug' => $slug,
                'rclone_remote' => 'yandex',
                'rclone_token' => null,
                'setup_step' => Server::STEP_CONNECT,
                'is_setup_complete' => false,
                'passtore_synced_at' => now(),
            ]);
            $server->syncFullBackupPath();
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($items),
        ];
    }

    private function guessKind(string $host, string $user): string
    {
        if (preg_match('/beget|timeweb|reg\.ru|hosting/i', $host)) {
            return Server::KIND_HOSTING;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return Server::KIND_VPS;
        }
        if (strtolower($user) === 'root') {
            return Server::KIND_VPS;
        }

        return Server::KIND_HOSTING;
    }
}
