<?php

namespace App\Services;

use App\Models\ModxConfig;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfigDiscoveryService
{
    public function __construct(
        private SshService $ssh,
        private ModxConfigParser $parser,
    ) {}

    /**
     * Синхронный поиск config.inc.php (как в passtore).
     * На Beget: сразу HOME/.../public_html/core/config — без фонового обхода деревьев.
     *
     * @return array{found: int, paths: list<string>}
     */
    public function discoverSync(Server $server): array
    {
        $server->markDiscoveryRunning();

        try {
            $configFiles = $this->findConfigFilesOnServer($server);
            $result = $this->persistConfigs($server, $configFiles);
            $server->markDiscoveryCompleted($result['found']);

            return $result;
        } catch (\Throwable $e) {
            $server->markDiscoveryFailed($this->toUtf8($e->getMessage()) ?? $e->getMessage());
            throw $e;
        }
    }

    /**
     * Команда как в passtore SshService::findAllConfigFiles — быстрый точечный поиск.
     *
     * @return list<string>
     */
    public function findConfigFilesOnServer(Server $server): array
    {
        // Управляющие конструкции нельзя склеивать через ";' — один скрипт целиком.
        $searchCommand = <<<'BASH'
set +e
files=""
for d in "$HOME"/*/public_html/core/config "$HOME"/public_html/core/config "$HOME"/web/*/public_html/core/config; do
  if [ -d "$d" ]; then
    found=$(find "$d" -maxdepth 1 -type f -name 'config.inc.php' 2>/dev/null)
    if [ -n "$found" ]; then
      files="${files}${found}
"
    fi
  fi
done
if [ -z "$files" ]; then
  found=$(find "$HOME" -maxdepth 6 -type f -path '*/public_html/core/config/config.inc.php' 2>/dev/null)
  if [ -n "$found" ]; then
    files="${files}${found}
"
  fi
fi
printf '%s' "$files"
BASH;

        $output = $this->ssh->exec($server, $searchCommand, 180);

        return array_values(array_unique(array_filter(
            array_map('trim', explode("\n", trim($output))),
            fn (string $line) => $line !== '' && str_ends_with($line, 'config.inc.php'),
        )));
    }

    /** @deprecated используйте discoverSync — оставлен для CLI/совместимости */
    public function startRemote(Server $server): int
    {
        $this->discoverSync($server);

        return 0;
    }

    /** @return 'running'|'done'|'failed' */
    public function pollRemote(Server $server): string
    {
        return match ($server->fresh()->config_discovery_status) {
            Server::DISCOVERY_RUNNING => 'running',
            Server::DISCOVERY_COMPLETED => 'done',
            default => 'failed',
        };
    }

    public function advanceDiscovery(Server $server): void
    {
        // Синхронный режим: прогресс только через discoverSync
    }

    public function stopRemote(Server $server): void
    {
        $server->update(['config_discovery_remote_pid' => null]);
    }

    /** @param list<string> $configFiles
     * @return array{found: int, paths: list<string>}
     */
    private function persistConfigs(Server $server, array $configFiles): array
    {
        $found = 0;
        $seen = [];

        DB::transaction(function () use ($server, $configFiles, &$found, &$seen) {
            foreach ($configFiles as $configPath) {
                if (! str_ends_with($configPath, 'config.inc.php')) {
                    continue;
                }

                try {
                    $suggestedRoot = $this->parser->resolveRootPath($configPath);
                } catch (\Throwable) {
                    continue;
                }

                $seen[] = $configPath;

                ModxConfig::query()->updateOrCreate(
                    [
                        'server_id' => $server->id,
                        'config_path' => $configPath,
                    ],
                    [
                        'suggested_root_path' => $suggestedRoot,
                        'label' => $this->parser->projectNameFromRoot($suggestedRoot),
                    ]
                );

                $found++;
            }

            if ($seen !== []) {
                ModxConfig::query()
                    ->where('server_id', $server->id)
                    ->whereNotIn('config_path', $seen)
                    ->delete();
            }

            $server->update(['last_discovered_at' => now()]);
        });

        return [
            'found' => $found,
            'paths' => $seen,
        ];
    }

    private function toUtf8(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1251');

        return $converted !== false ? $converted : mb_scrub($value, 'UTF-8');
    }
}
