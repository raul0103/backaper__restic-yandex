#!/usr/bin/env php
<?php
/**
 * CLI helper: parse MODX / WordPress / Laravel DB credentials from a config file.
 * Compatible with PHP 5.6+ (Beget CLI default).
 *
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

function php_var($c, $n)
{
    if (preg_match('/\$'.preg_quote($n, '/')."\s*=\s*['\"]([^'\"]*)['\"]\s*;/", $c, $m)) {
        return $m[1];
    }

    return null;
}

function def_const($c, $n)
{
    // define('DB_NAME', 'x') or define( 'DB_NAME' , "x" )
    if (preg_match("/define\s*\(\s*['\"]".preg_quote($n, '/')."['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $c, $m)) {
        return $m[1];
    }

    return null;
}

function env_val($c, $n)
{
    if (! preg_match('/^'.preg_quote($n, '/').'\s*=\s*(.*)$/m', $c, $m)) {
        return null;
    }
    $v = trim($m[1]);
    $len = strlen($v);
    if ($len >= 2) {
        $q = $v[0];
        if (($q === '"' || $q === "'") && substr($v, -1) === $q) {
            $v = substr($v, 1, -1);
        }
    }

    return $v;
}

function label_from($path, $strip)
{
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $path))));
    $out = array();
    foreach ($parts as $p) {
        if (! in_array($p, $strip, true)) {
            $out[] = $p;
        }
    }

    return $out !== array() ? (string) end($out) : basename(dirname($path));
}

$host = null;
$name = null;
$user = null;
$pass = null;
$source = null;
$label = null;

if ($base === 'config.inc.php') {
    $name = php_var($c, 'dbase');
    $user = php_var($c, 'database_user');
    $host = php_var($c, 'database_server');
    if ($host === null || $host === '') {
        $host = 'localhost';
    }
    $pass = php_var($c, 'database_password');
    if ($pass === null) {
        $pass = '';
    }
    $source = 'modx';
    $label = label_from($path, array('public_html', 'core', 'config'));
} elseif ($base === 'wp-config.php') {
    $name = def_const($c, 'DB_NAME');
    $user = def_const($c, 'DB_USER');
    $host = def_const($c, 'DB_HOST');
    if ($host === null || $host === '') {
        $host = 'localhost';
    }
    $pass = def_const($c, 'DB_PASSWORD');
    if ($pass === null) {
        $pass = '';
    }
    $source = 'wordpress';
    $label = label_from($path, array('public_html', 'wp-config.php'));
} elseif ($base === '.env') {
    $name = env_val($c, 'DB_DATABASE');
    $user = env_val($c, 'DB_USERNAME');
    $host = env_val($c, 'DB_HOST');
    if ($host === null || $host === '') {
        $host = 'localhost';
    }
    $pass = env_val($c, 'DB_PASSWORD');
    if ($pass === null) {
        $pass = '';
    }
    $source = 'laravel';
    $label = label_from($path, array('.env', 'public_html', 'current'));
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

echo implode("\t", array($host, $name, $user, $pass, $source, $label)), "\n";
