<?php

namespace App\Services;

use RuntimeException;

class ModxConfigParser
{
    /**
     * @return array{
     *     database_server: string,
     *     database_name: string,
     *     database_user: string,
     *     database_password: string,
     *     table_prefix: string,
     *     root_path: string,
     *     config_path: string
     * }
     */
    public function parse(string $configPath, string $contents): array
    {
        $databaseServer = $this->extractVariable($contents, 'database_server');
        $databaseName = $this->extractVariable($contents, 'dbase');
        $databaseUser = $this->extractVariable($contents, 'database_user');
        $databasePassword = $this->extractVariable($contents, 'database_password');
        $tablePrefix = $this->extractVariable($contents, 'table_prefix') ?: 'modx_';

        if ($databaseName === null || $databaseUser === null) {
            throw new RuntimeException("Cannot parse database settings from {$configPath}");
        }

        $rootPath = $this->resolveRootPath($configPath);

        return [
            'database_server' => $databaseServer ?: 'localhost',
            'database_name' => $databaseName,
            'database_user' => $databaseUser,
            'database_password' => $databasePassword ?? '',
            'table_prefix' => $tablePrefix,
            'root_path' => $rootPath,
            'config_path' => $configPath,
        ];
    }

    private function extractVariable(string $contents, string $name): ?string
    {
        $pattern = '/\$'.preg_quote($name, '/')."\s*=\s*['\"]([^'\"]*)['\"]\s*;/";

        if (preg_match($pattern, $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function resolveRootPath(string $configPath): string
    {
        $normalized = str_replace('\\', '/', $configPath);
        $marker = '/core/config/';

        $pos = strpos($normalized, $marker);
        if ($pos === false) {
            throw new RuntimeException("Unexpected MODX config path: {$configPath}");
        }

        return substr($normalized, 0, $pos);
    }

    public function projectNameFromRoot(string $rootPath): string
    {
        $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');

        return basename($rootPath) ?: 'modx-site';
    }
}
