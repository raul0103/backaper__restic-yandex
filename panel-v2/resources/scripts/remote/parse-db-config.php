#!/usr/bin/env php
<?php
/**
 * CLI helper: parse MODX / WordPress / Laravel DB credentials from a config file.
 * Usage: php parse-db-config.php /path/to/config.inc.php
 * Output (TSV): host\tname\tuser\tpassword\tsource\tlabel
 */
if ($argc < 2) {
    fwrite(STDERR, "Usage: php parse-db-config.php <config-path>\n");
    exit(1);
}

$path = $argv[1];
$c = @file_get_contents($path);
if ($c === false || $c === '') {
    fwrite(STDERR, "empty or unreadable\n");
    exit(1);
}

$base = basename($path);

function php_var(string $c, string $n): ?string
{
    if (preg_match('/\$'.preg_quote($n, '/')."\s*=\s*['\"]([^'\"]*)['\"]\s*;/", $c, $m)) {
        return $m[1];
    }

    return null;
}

function def_const(string $c, string $n): ?string
{
    if (preg_match("/define\s*\(\s*['\"]".preg_quote($n, '/')."['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $c, $m)) {
        return $m[1];
    }

    return null;
}

function env_val(string $c, string $n): ?string
{
    if (! preg_match('/^'.preg_quote($n, '/').'\s*=\s*(.*)$/m', $c, $m)) {
        return null;
    }
    $v = trim($m[1]);
    if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
        || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
        $v = substr($v, 1, -1);
    }

    return $v;
}

/** @param list<string> $strip */
function label_from(string $path, array $strip): string
{
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $path))));
    $parts = array_values(array_filter($parts, fn ($p) => ! in_array($p, $strip, true)));

    return $parts !== [] ? (string) end($parts) : basename(dirname($path));
}

$host = $name = $user = $pass = $source = $label = null;

if ($base === 'config.inc.php') {
    $name = php_var($c, 'dbase');
    $user = php_var($c, 'database_user');
    $host = php_var($c, 'database_server') ?: 'localhost';
    $pass = php_var($c, 'database_password') ?? '';
    $source = 'modx';
    $label = label_from($path, ['public_html', 'core', 'config']);
} elseif ($base === 'wp-config.php') {
    $name = def_const($c, 'DB_NAME');
    $user = def_const($c, 'DB_USER');
    $host = def_const($c, 'DB_HOST') ?: 'localhost';
    $pass = def_const($c, 'DB_PASSWORD') ?? '';
    $source = 'wordpress';
    $label = label_from($path, ['public_html', 'wp-config.php']);
} elseif ($base === '.env') {
    $name = env_val($c, 'DB_DATABASE');
    $user = env_val($c, 'DB_USERNAME');
    $host = env_val($c, 'DB_HOST') ?: 'localhost';
    $pass = env_val($c, 'DB_PASSWORD') ?? '';
    $source = 'laravel';
    $label = label_from($path, ['.env', 'public_html', 'current']);
    if ($name === null || $name === '') {
        fwrite(STDERR, "no DB_DATABASE\n");
        exit(1);
    }
} else {
    fwrite(STDERR, "unknown config type\n");
    exit(1);
}

if ($name === null || $user === null) {
    fwrite(STDERR, "parse failed\n");
    exit(1);
}

echo implode("\t", [$host, $name, $user, $pass, $source, $label]), "\n";
