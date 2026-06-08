<?php

namespace App\Services;

use App\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;

class SshService
{
    public function connect(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->ssh_port);
        $ssh->setTimeout(300);
        $this->login($ssh, $server);

        return $ssh;
    }

    public function sftp(Server $server): SFTP
    {
        $sftp = new SFTP($server->host, $server->ssh_port);
        $sftp->setTimeout(300);
        $this->login($sftp, $server);

        return $sftp;
    }

    public function exec(Server $server, string $command, int $timeout = 300): string
    {
        $ssh = new SSH2($server->host, $server->ssh_port);
        $ssh->setTimeout($timeout);
        $this->login($ssh, $server);

        $output = $ssh->exec($command);

        if ($output === false) {
            throw new RuntimeException('SSH command returned no output');
        }

        return trim($output);
    }

    public function homeDir(Server $server): string
    {
        return $this->exec($server, 'printf %s "$HOME"');
    }

    public function upload(Server $server, string $remotePath, string $contents): void
    {
        $remotePath = str_replace('~', $this->homeDir($server), $remotePath);
        $sftp = $this->sftp($server);
        $dir = dirname($remotePath);

        if (! $sftp->is_dir($dir)) {
            $this->mkdirRecursive($sftp, $dir);
        }

        if (! $sftp->put($remotePath, $contents)) {
            throw new RuntimeException("Failed to upload {$remotePath}");
        }
    }

    public function read(Server $server, string $remotePath): string
    {
        $remotePath = str_replace('~', $this->homeDir($server), $remotePath);
        $sftp = $this->sftp($server);
        $contents = $sftp->get($remotePath);

        if ($contents === false) {
            throw new RuntimeException("Failed to read {$remotePath}");
        }

        return $contents;
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
