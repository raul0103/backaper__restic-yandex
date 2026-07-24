<?php

namespace App\Services;

use App\Models\DatabaseCredential;
use App\Models\Server;

/**
 * Ищет доступы к БД в типичных конфигах сайтов (MODX, Laravel, WordPress).
 */
class DatabaseDiscoveryService
{
    public function __construct(
        private SshService $ssh,
        private DatabaseConfigParser $parser,
    ) {}

    /** @return array{found: int, skipped: int, scanned: int} */
    public function discover(Server $server): array
    {
        $paths = $this->findConfigFiles($server);
        $found = 0;
        $skipped = 0;
        $scanned = count($paths);

        if ($paths === []) {
            $server->update(['last_discovered_at' => now()]);

            return ['found' => 0, 'skipped' => 0, 'scanned' => 0];
        }

        $contents = $this->ssh->readMany($server, $paths);

        foreach ($paths as $configPath) {
            $body = $contents[$configPath] ?? null;
            if ($body === null || $body === '') {
                $skipped++;

                continue;
            }

            try {
                $parsed = $this->parser->parse($configPath, $body);

                DatabaseCredential::query()->updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'database_name' => $parsed['database_name'],
                        'database_user' => $parsed['database_user'],
                    ],
                    [
                        'label' => $parsed['label'],
                        'source' => $parsed['source'],
                        'config_path' => $configPath,
                        'database_server' => $parsed['database_server'],
                        'database_password' => $parsed['database_password'],
                        'table_prefix' => $parsed['table_prefix'],
                        'is_enabled' => true,
                    ]
                );
                $found++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        $server->update(['last_discovered_at' => now()]);

        return compact('found', 'skipped', 'scanned');
    }

    /** @return list<string> */
    private function findConfigFiles(Server $server): array
    {
        $script = $server->isVps()
            ? $this->vpsFindScript()
            : $this->hostingFindScript();

        $output = $this->ssh->exec($server, $script, 180);
        if ($output === '') {
            return [];
        }

        $paths = array_values(array_filter(array_map('trim', explode("\n", $output))));

        // Защита от слишком длинного списка (ложные .env вглубине)
        return array_slice(array_values(array_unique($paths)), 0, 250);
    }

    private function hostingFindScript(): string
    {
        return <<<'BASH'
set +e
HOME_DIR="${HOME:-/home/$USER}"
found=""

for d in \
  "$HOME_DIR"/*/public_html/core/config \
  "$HOME_DIR"/web/*/public_html/core/config \
  "$HOME_DIR"/domains/*/public_html/core/config
do
  [ -f "$d/config.inc.php" ] && found="$found
$d/config.inc.php"
done

for f in \
  "$HOME_DIR"/*/public_html/wp-config.php \
  "$HOME_DIR"/web/*/public_html/wp-config.php \
  "$HOME_DIR"/domains/*/public_html/wp-config.php
do
  [ -f "$f" ] && found="$found
$f"
done

for f in \
  "$HOME_DIR"/*/public_html/.env \
  "$HOME_DIR"/*/.env \
  "$HOME_DIR"/web/*/.env \
  "$HOME_DIR"/web/*/public_html/.env
do
  [ -f "$f" ] && found="$found
$f"
done

printf '%s' "$found" | sed '/^$/d' | sort -u
BASH;
    }

    private function vpsFindScript(): string
    {
        // VPS: Hestia/Vesta, /var/www, произвольная вложенность — find с prune
        return <<<'BASH'
set +e
export LC_ALL=C
roots="/var/www /home /srv /opt"
# если HOME не внутри /home — тоже смотрим
case "${HOME:-}" in
  /home/*|/root|"") ;;
  *) roots="$roots $HOME" ;;
esac

# shellcheck disable=SC2086
find $roots \
  \( -name node_modules -o -name vendor -o -name .git -o -name cache -o -name .cache -o -name .npm -o -name storage \) -prune -o \
  \( \
    -path '*/core/config/config.inc.php' -type f -print -o \
    -name 'wp-config.php' -type f -print -o \
    -name '.env' -type f -print \
  \) \
  2>/dev/null \
| grep -Ev '/(vendor|node_modules|\.git|cache|\.cache)/' \
| grep -Ev '/\.env\.' \
| head -n 250 \
| sort -u
BASH;
    }
}
