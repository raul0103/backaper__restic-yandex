<?php

namespace App\Services;

use App\Models\ModxConfig;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfigDiscoveryService
{
    /** @var array<int, string> */
    private array $remoteBaseCache = [];

    /** @var list<string> */
    private const PRUNE_DIR_NAMES = [
        'node_modules',
        'vendor',
        '.git',
        '.svn',
        'assets',
        'static',
        'cache',
        'tmp',
        '.cache',
        '.npm',
        'bower_components',
        'core/cache',
        'core/packages',
    ];

    public function __construct(
        private SshService $ssh,
        private ModxConfigParser $parser,
    ) {}

    public function remoteBaseDir(Server $server): string
    {
        if (isset($this->remoteBaseCache[$server->id])) {
            return $this->remoteBaseCache[$server->id];
        }

        $home = rtrim($this->ssh->homeDir($server), '/');
        $this->remoteBaseCache[$server->id] = $home.'/.backaper/discovery-'.$server->id;

        return $this->remoteBaseCache[$server->id];
    }

    public function startRemote(Server $server): int
    {
        $base = $this->remoteBaseDir($server);
        $scriptPath = $base.'/run.sh';
        $outPath = $base.'/results.txt';
        $pidPath = $base.'/pid';
        $donePath = $base.'/done';

        $this->ssh->upload($server, $scriptPath, $this->buildRemoteScript($server, $outPath, $donePath));

        $start = <<<BASH
base={$base}
mkdir -p "\$base"
rm -f "{$outPath}" "{$donePath}" "{$pidPath}"
chmod +x "{$scriptPath}"
nohup bash "{$scriptPath}" > "\$base/run.log" 2>&1 &
echo \$! > "{$pidPath}"
cat "{$pidPath}"
BASH;

        $output = trim($this->ssh->exec($server, $start, 30));
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        $pid = (int) ($lines[array_key_last($lines)] ?? 0);

        if ($pid <= 0) {
            $log = '';
            try {
                $log = trim($this->ssh->read($server, $base.'/run.log'));
            } catch (\Throwable) {
                // ignore
            }

            throw new RuntimeException(
                'Не удалось запустить поиск на сервере'
                .($output !== '' ? ': '.$output : '')
                .($log !== '' ? ' | log: '.$log : ''),
            );
        }

        $server->update(['config_discovery_remote_pid' => $pid]);

        return $pid;
    }

    /** @return 'running'|'done'|'failed' */
    public function pollRemote(Server $server): string
    {
        $base = $this->remoteBaseDir($server);
        $pidPath = $base.'/pid';
        $donePath = $base.'/done';
        $outPath = $base.'/results.txt';

        $check = <<<BASH
base={$base}
if [ -f "{$donePath}" ]; then
  echo DONE
  exit 0
fi
if [ -f "{$pidPath}" ] && kill -0 "\$(cat "{$pidPath}")" 2>/dev/null; then
  echo RUNNING
  exit 0
fi
if [ -f "{$outPath}" ]; then
  echo DONE
  exit 0
fi
echo FAILED
BASH;

        $state = trim($this->ssh->exec($server, $check, 30));

        return match ($state) {
            'RUNNING' => 'running',
            'DONE' => 'done',
            default => 'failed',
        };
    }

    /** @return array{found: int, paths: list<string>} */
    public function collectRemoteResults(Server $server): array
    {
        $outPath = $this->remoteBaseDir($server).'/results.txt';
        $output = $this->ssh->read($server, $outPath);
        $configFiles = array_values(array_filter(array_map('trim', explode("\n", $output))));

        return $this->persistConfigs($server, $configFiles);
    }

    public function stopRemote(Server $server): void
    {
        $base = $this->remoteBaseDir($server);
        $pidPath = $base.'/pid';

        try {
            $this->ssh->exec($server, <<<BASH
if [ -f "{$pidPath}" ]; then
  kill "\$(cat "{$pidPath}")" 2>/dev/null || true
  pkill -P "\$(cat "{$pidPath}")" 2>/dev/null || true
fi
pkill -f 'backaper-discovery-{$server->id}' 2>/dev/null || true
rm -f "{$pidPath}"
BASH, 15);
        } catch (\Throwable) {
            // best effort
        }

        $server->update(['config_discovery_remote_pid' => null]);
    }

    public function advanceDiscovery(Server $server): void
    {
        if (! $server->isDiscoveryRunning()) {
            return;
        }

        try {
            $state = $this->pollRemote($server);

            if ($state === 'running') {
                return;
            }

            if ($state === 'failed') {
                $log = $this->readRemoteLogTail($server);
                $server->markDiscoveryFailed(
                    'Поиск на сервере завершился с ошибкой.'
                    .($log !== '' ? ' '.$log : ' Смотрите ~/.backaper/discovery-'.$server->id.'/run.log'),
                );

                return;
            }

            $result = $this->collectRemoteResults($server);
            $server->markDiscoveryCompleted($result['found']);
        } catch (\Throwable $e) {
            $server->markDiscoveryFailed($this->toUtf8($e->getMessage()));
        }
    }

    private function readRemoteLogTail(Server $server): string
    {
        try {
            $log = trim($this->ssh->read($server, $this->remoteBaseDir($server).'/run.log'));

            return $this->toUtf8(mb_substr($log, -400));
        } catch (\Throwable) {
            return '';
        }
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

    /** @param list<string> $configFiles
     * @return array{found: int, paths: list<string>}
     */
    private function persistConfigs(Server $server, array $configFiles): array
    {
        $found = 0;
        $seen = [];

        DB::transaction(function () use ($server, $configFiles, &$found, &$seen) {
            foreach ($configFiles as $configPath) {
                if (! str_ends_with(basename($configPath), '.inc.php')) {
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

    private function buildRemoteScript(Server $server, string $outPath, string $donePath): string
    {
        $pruneParts = array_map(
            fn (string $name) => str_contains($name, '/')
                ? "-path '*/{$name}'"
                : "-name '{$name}'",
            self::PRUNE_DIR_NAMES,
        );
        $pruneExpr = implode(' -o ', $pruneParts);
        $marker = 'backaper-discovery-'.$server->id;

        return <<<BASH
#!/bin/bash
# {$marker}

home=\$(printf %s "\$HOME")
tmp=\$(mktemp)
roots=\$(mktemp)
trap 'rm -f "\$tmp" "\$roots"' EXIT

add_root() {
  local p="\${1%/}"
  [ -z "\$p" ] || [ ! -d "\$p" ] && return
  echo "\$p" >> "\$roots"
  local parent="\$(dirname "\$p")"
  if [ "\$parent" != "\$p" ] && [ -d "\$parent" ]; then
    echo "\$parent" >> "\$roots"
  fi
}

search_modx() {
  local root="\$1"
  [ -d "\$root" ] || return 0
  find "\$root" \\
    \\( {$pruneExpr} \\) -prune -o \\
    -type f -path '*/core/config/*.inc.php' -print 2>/dev/null
}

parse_vhost_paths() {
  sed -E 's/#.*\$//' \\
    | sed -E 's/^[[:space:]]*(root|chdir|DocumentRoot)[[:space:]]+//' \\
    | sed -E 's/[;"].*\$//' \\
    | tr -d "'"
}

# Hestia / типичный хостинг: ~/web/domain/public_html
for pub in "\$home/web"/*/public_html; do
  [ -d "\$pub" ] && add_root "\$pub"
done

# Nginx/Apache/PHP конфиги пользователя (Hestia: ~/conf/web/...)
if [ -d "\$home/conf" ]; then
  grep -rhE '(root|chdir|DocumentRoot)[[:space:]]+' "\$home/conf" 2>/dev/null \\
    | parse_vhost_paths \\
    | while IFS= read -r p; do
        add_root "\$p"
      done
fi

# PHP-FPM pool.d — chdir = document root
grep -rhE '^[[:space:]]*chdir[[:space:]]*=' /etc/php/*/fpm/pool.d/ 2>/dev/null \\
  | sed -E 's/^[[:space:]]*chdir[[:space:]]*=[[:space:]]*//' \\
  | sed -E 's/[;"].*\$//' \\
  | tr -d ' "' \\
  | while IFS= read -r p; do
      case "\$p" in "\$home"/*) add_root "\$p" ;; esac
    done

# Другие сайты на сервере (если доступны на чтение)
for pub in /home/*/web/*/public_html; do
  [ -d "\$pub" ] && add_root "\$pub"
done
for extra in /var/www /srv/www; do
  [ -d "\$extra" ] && add_root "\$extra"
done

# Поиск в собранных корнях
if [ -s "\$roots" ]; then
  sort -u "\$roots" | while IFS= read -r root; do
    search_modx "\$root" >> "\$tmp"
  done
fi

# Запасной вариант: pruned find в \$HOME
if [ ! -s "\$tmp" ]; then
  search_modx "\$home" >> "\$tmp"
fi

sort -u "\$tmp" | sed '/^$/d' > "{$outPath}"
echo done > "{$donePath}"
BASH;
    }
}
