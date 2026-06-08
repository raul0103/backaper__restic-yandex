<?php

namespace App\Services;

use App\Models\ModxConfig;
use App\Models\ProjectDatabase;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class DatabaseExtractionService
{
    public function __construct(
        private SshService $ssh,
        private ModxConfigParser $parser,
    ) {}

    /** @return array{extracted: int, failed: int} */
    public function extract(Server $server): array
    {
        $configs = ModxConfig::query()->where('server_id', $server->id)->get();
        $extracted = 0;
        $failed = 0;

        DB::transaction(function () use ($server, $configs, &$extracted, &$failed) {
            foreach ($configs as $config) {
                try {
                    $contents = $this->ssh->read($server, $config->config_path);
                    $parsed = $this->parser->parse($config->config_path, $contents);

                    ProjectDatabase::query()->updateOrCreate(
                        ['modx_config_id' => $config->id],
                        [
                            'server_id' => $server->id,
                            'database_server' => $parsed['database_server'],
                            'database_name' => $parsed['database_name'],
                            'database_user' => $parsed['database_user'],
                            'database_password' => $parsed['database_password'],
                            'table_prefix' => $parsed['table_prefix'],
                        ]
                    );

                    $config->update([
                        'suggested_root_path' => $parsed['root_path'],
                        'label' => $this->parser->projectNameFromRoot($parsed['root_path']),
                    ]);

                    $extracted++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        return compact('extracted', 'failed');
    }
}
