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

/** Для вложенных .env: web/docker/passtore/backend → passtore/backend */
function env_label($path)
{
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $path))));
    $parts = array_values(array_filter($parts, function ($p) {
        return $p !== '.env' && $p !== 'public_html' && $p !== 'current' && $p !== 'home' && $p !== 'var' && $p !== 'www';
    }));
    $n = count($parts);
    if ($n >= 2) {
        return $parts[$n - 2].'/'.$parts[$n - 1];
    }

    return $n > 0 ? $parts[$n - 1] : basename(dirname($path));
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
    // Laravel / Symfony / generic DB_*
    $name = env_val($c, 'DB_DATABASE');
    $user = env_val($c, 'DB_USERNAME');
    $host = env_val($c, 'DB_HOST');
    $pass = env_val($c, 'DB_PASSWORD');
    $source = 'laravel';

    // Docker-style MYSQL_*
    if ($name === null || $name === '') {
        $name = env_val($c, 'MYSQL_DATABASE');
        if ($name !== null && $name !== '') {
            $source = 'docker-mysql';
            if ($user === null || $user === '') {
                $user = env_val($c, 'MYSQL_USER');
            }
            if ($user === null || $user === '') {
                $user = 'root';
            }
            if ($pass === null || $pass === '') {
                $p = env_val($c, 'MYSQL_PASSWORD');
                if ($p === null || $p === '') {
                    $p = env_val($c, 'MYSQL_ROOT_PASSWORD');
                }
                $pass = $p;
            }
            if ($host === null || $host === '') {
                $host = env_val($c, 'MYSQL_HOST');
            }
        }
    }

    // DATABASE_URL=mysql://user:pass@host:3306/dbname
    if (($name === null || $name === '') && ($url = env_val($c, 'DATABASE_URL')) !== null && $url !== '') {
        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['scheme'])
            && in_array(strtolower($parts['scheme']), array('mysql', 'mysqli', 'mariadb'), true)
        ) {
            $source = 'database-url';
            $name = isset($parts['path']) ? ltrim($parts['path'], '/') : null;
            if ($user === null || $user === '') {
                $user = isset($parts['user']) ? rawurldecode($parts['user']) : null;
            }
            if ($pass === null || $pass === '') {
                $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
            }
            if ($host === null || $host === '') {
                $host = isset($parts['host']) ? $parts['host'] : 'localhost';
                if (isset($parts['port'])) {
                    $host .= ':'.$parts['port'];
                }
            }
        }
    }

    if ($host === null || $host === '') {
        $host = 'localhost';
    }
    if ($pass === null) {
        $pass = '';
    }
    $label = env_label($path);
    if ($name === null || $name === '') {
        fwrite(STDERR, "no DB_DATABASE/MYSQL_DATABASE/DATABASE_URL\n");
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
