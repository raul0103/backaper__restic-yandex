<?php

namespace App\Services;

use App\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

class SshService
{
    /** @var array<int, string> */
    private array $homeDirCache = [];

    /** @var array<int, SFTP> */
    private array $sftpCache = [];

    public function connect(Server $server): SSH2
    {
        return $this->sftp($server);
    }

    public function sftp(Server $server): SFTP
    {
        $id = (int) $server->id;
        if (isset($this->sftpCache[$id]) && $this->sftpCache[$id]->isConnected()) {
            return $this->sftpCache[$id];
        }

        $this->sftpCache[$id] = $this->openSftp($server);

        return $this->sftpCache[$id];
    }

    public function exec(Server $server, string $command, int $timeout = 300): string
    {
        $sftp = $this->sftp($server);
        $sftp->setTimeout($timeout);

        $output = $sftp->exec($command);

        if ($output === false) {
            throw new RuntimeException('SSH command returned no output');
        }

        return trim($output);
    }

    public function homeDir(Server $server): string
    {
        $id = (int) $server->id;
        if (! isset($this->homeDirCache[$id])) {
            $this->homeDirCache[$id] = $this->exec($server, 'printf %s "$HOME"', 30);
        }

        return $this->homeDirCache[$id];
    }

    public function upload(Server $server, string $remotePath, string $contents): void
    {
        $this->uploadMany($server, [$remotePath => $contents]);
    }

    /**
     * Несколько файлов по одному SFTP-соединению (Beget рвёт частые новые SSH).
     *
     * @param  array<string, string>  $files  remotePath => contents
     */
    public function uploadMany(Server $server, array $files): void
    {
        if ($files === []) {
            return;
        }

        $sftp = $this->sftp($server);

        foreach ($files as $remotePath => $contents) {
            $path = $this->expandPath($server, $remotePath);
            if (str_ends_with($path, '.sh') || str_ends_with($path, '.env') || str_ends_with($path, '.bash')) {
                $contents = str_replace(["\r\n", "\r"], "\n", $contents);
            }

            $dir = dirname($path);
            if (! $sftp->is_dir($dir)) {
                $this->mkdirRecursive($sftp, $dir);
            }

            if (! $sftp->put($path, $contents)) {
                throw new RuntimeException("Failed to upload {$path}");
            }
        }
    }

    public function read(Server $server, string $remotePath): string
    {
        $results = $this->readMany($server, [$remotePath]);
        $remotePath = $this->expandPath($server, $remotePath);

        if (! isset($results[$remotePath])) {
            throw new RuntimeException("Failed to read {$remotePath}");
        }

        return $results[$remotePath];
    }

    /**
     * @param  list<string>  $remotePaths
     * @return array<string, string>
     */
    public function readMany(Server $server, array $remotePaths): array
    {
        if ($remotePaths === []) {
            return [];
        }

        $sftp = $this->sftp($server);
        $out = [];

        foreach ($remotePaths as $remotePath) {
            $path = $this->expandPath($server, $remotePath);
            $contents = $sftp->get($path);
            if ($contents !== false) {
                $out[$path] = $contents;
            }
        }

        return $out;
    }

    /** Сбросить кэш соединения (после долгого простоя / обрыва). */
    public function disconnect(Server $server): void
    {
        $id = (int) $server->id;
        if (isset($this->sftpCache[$id])) {
            try {
                $this->sftpCache[$id]->disconnect();
            } catch (Throwable) {
            }
            unset($this->sftpCache[$id]);
        }
    }

    private function openSftp(Server $server): SFTP
    {
        $last = null;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $sftp = new SFTP($server->host, (int) $server->ssh_port);
                $sftp->setTimeout(300);
                $this->login($sftp, $server);

                return $sftp;
            } catch (Throwable $e) {
                $last = $e;
                $msg = $e->getMessage();
                $refused = str_contains($msg, '10061')
                    || str_contains($msg, 'Connection refused')
                    || str_contains($msg, 'отверг запрос');
                if (! $refused || $attempt === 4) {
                    throw $e;
                }
                usleep(750_000 * $attempt);
            }
        }

        throw $last ?? new RuntimeException('SSH connect failed');
    }

    private function expandPath(Server $server, string $remotePath): string
    {
        if (str_contains($remotePath, '~')) {
            return str_replace('~', $this->homeDir($server), $remotePath);
        }

        return $remotePath;
    }

    private function login(SSH2|SFTP $client, Server $server): void
    {
        $target = "{$server->ssh_user}@{$server->host}";

        if ($server->usesPasswordAuth()) {
            if (empty($server->ssh_password)) {
                throw new RuntimeException("SSH password not set for {$target}");
            }
            if (! $client->login($server->ssh_user, $server->ssh_password)) {
                throw new RuntimeException("SSH login failed (password) for {$target}");
            }

            return;
        }

        if (empty($server->ssh_private_key)) {
            throw new RuntimeException("SSH private key not set for {$target}");
        }

        $key = PublicKeyLoader::load($server->ssh_private_key);

        if (! $client->login($server->ssh_user, $key)) {
            throw new RuntimeException("SSH login failed (key) for {$target}. Add public key to authorized_keys.");
        }
    }

    private function mkdirRecursive(SFTP $sftp, string $path): void
    {
        $parts = explode('/', ltrim($path, '/'));
        $current = '';

        foreach ($parts as $part) {
            $current .= '/'.$part;
            if (! $sftp->is_dir($current)) {
                $sftp->mkdir($current);
            }
        }
    }
}
