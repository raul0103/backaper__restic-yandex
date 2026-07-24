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

    /** @return array{found: int, skipped: int} */
    public function discover(Server $server): array
    {
        $paths = $this->findConfigFiles($server);
        $found = 0;
        $skipped = 0;

        if ($paths === []) {
            $server->update(['last_discovered_at' => now()]);

            return ['found' => 0, 'skipped' => 0];
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

        return compact('found', 'skipped');
    }

    /** @return list<string> */
    private function findConfigFiles(Server $server): array
    {
        $script = <<<'BASH'
set +e
HOME_DIR="${HOME:-/home/$USER}"
found=""

for d in "$HOME_DIR"/*/public_html/core/config "$HOME_DIR"/web/*/public_html/core/config /var/www/*/core/config /var/www/*/*/core/config; do
  [ -f "$d/config.inc.php" ] && found="$found
$d/config.inc.php"
done

for f in "$HOME_DIR"/*/public_html/wp-config.php "$HOME_DIR"/web/*/public_html/wp-config.php /var/www/*/wp-config.php /var/www/*/*/wp-config.php; do
  [ -f "$f" ] && found="$found
$f"
done

for f in "$HOME_DIR"/*/public_html/.env "$HOME_DIR"/*/.env "$HOME_DIR"/web/*/.env /var/www/*/.env /var/www/*/current/.env; do
  [ -f "$f" ] && found="$found
$f"
done

printf '%s' "$found" | sed '/^$/d' | sort -u
BASH;

        $output = $this->ssh->exec($server, $script, 120);
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $output))));
    }
}
