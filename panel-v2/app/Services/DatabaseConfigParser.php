<?php

namespace App\Services;

use RuntimeException;

class DatabaseConfigParser
{
    /**
     * @return array{
     *     source: string,
     *     label: string,
     *     database_server: string,
     *     database_name: string,
     *     database_user: string,
     *     database_password: string,
     *     table_prefix: ?string
     * }
     */
    public function parse(string $configPath, string $contents): array
    {
        $base = basename($configPath);

        if ($base === 'config.inc.php') {
            return $this->parseModx($configPath, $contents);
        }
        if ($base === 'wp-config.php') {
            return $this->parseWordpress($configPath, $contents);
        }
        if ($base === '.env') {
            return $this->parseLaravelEnv($configPath, $contents);
        }

        throw new RuntimeException("Unknown config type: {$configPath}");
    }

    private function parseModx(string $configPath, string $contents): array
    {
        $name = $this->phpVar($contents, 'dbase');
        $user = $this->phpVar($contents, 'database_user');
        if ($name === null || $user === null) {
            throw new RuntimeException("Cannot parse MODX DB from {$configPath}");
        }

        return [
            'source' => 'modx',
            'label' => $this->labelFromPath($configPath, ['public_html', 'core', 'config']),
            'database_server' => $this->phpVar($contents, 'database_server') ?: 'localhost',
            'database_name' => $name,
            'database_user' => $user,
            'database_password' => $this->phpVar($contents, 'database_password') ?? '',
            'table_prefix' => $this->phpVar($contents, 'table_prefix') ?: 'modx_',
        ];
    }

    private function parseWordpress(string $configPath, string $contents): array
    {
        $name = $this->defineConst($contents, 'DB_NAME');
        $user = $this->defineConst($contents, 'DB_USER');
        if ($name === null || $user === null) {
            throw new RuntimeException("Cannot parse WP DB from {$configPath}");
        }

        return [
            'source' => 'wordpress',
            'label' => $this->labelFromPath($configPath, ['public_html', 'wp-config.php']),
            'database_server' => $this->defineConst($contents, 'DB_HOST') ?: 'localhost',
            'database_name' => $name,
            'database_user' => $user,
            'database_password' => $this->defineConst($contents, 'DB_PASSWORD') ?? '',
            'table_prefix' => $this->phpVar($contents, 'table_prefix') ?: 'wp_',
        ];
    }

    private function parseLaravelEnv(string $configPath, string $contents): array
    {
        $name = $this->envVal($contents, 'DB_DATABASE');
        $user = $this->envVal($contents, 'DB_USERNAME');
        if ($name === null || $name === '' || $user === null) {
            throw new RuntimeException("Cannot parse Laravel DB from {$configPath}");
        }

        return [
            'source' => 'laravel',
            'label' => $this->labelFromPath($configPath, ['.env', 'public_html', 'current']),
            'database_server' => $this->envVal($contents, 'DB_HOST') ?: 'localhost',
            'database_name' => $name,
            'database_user' => $user,
            'database_password' => $this->envVal($contents, 'DB_PASSWORD') ?? '',
            'table_prefix' => null,
        ];
    }

    private function phpVar(string $contents, string $name): ?string
    {
        $pattern = '/\$'.preg_quote($name, '/')."\s*=\s*['\"]([^'\"]*)['\"]\s*;/";

        return preg_match($pattern, $contents, $m) ? $m[1] : null;
    }

    private function defineConst(string $contents, string $name): ?string
    {
        $pattern = "/define\s*\(\s*['\"]".preg_quote($name, '/')."['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/";

        return preg_match($pattern, $contents, $m) ? $m[1] : null;
    }

    private function envVal(string $contents, string $name): ?string
    {
        $pattern = '/^'.preg_quote($name, '/').'\s*=\s*(.*)$/m';
        if (! preg_match($pattern, $contents, $m)) {
            return null;
        }
        $val = trim($m[1]);
        if ((str_starts_with($val, '"') && str_ends_with($val, '"'))
            || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        return $val;
    }

    /** @param list<string> $strip */
    private function labelFromPath(string $configPath, array $strip): string
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $configPath))));
        $parts = array_values(array_filter($parts, fn ($p) => ! in_array($p, $strip, true)));

        return $parts !== [] ? (string) end($parts) : basename(dirname($configPath));
    }
}
