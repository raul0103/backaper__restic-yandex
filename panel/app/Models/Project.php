<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const DEFAULT_EXCLUSIONS = [
        'core/cache/**',
        'core/packages/**',
        '**/node_modules/**',
        '**/.git/**',
    ];

    protected $fillable = [
        'server_id',
        'modx_config_id',
        'project_database_id',
        'name',
        'root_path',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function modxConfig(): BelongsTo
    {
        return $this->belongsTo(ModxConfig::class);
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(ProjectDatabase::class, 'project_database_id');
    }

    public function exclusionRules(): HasMany
    {
        return $this->hasMany(ProjectExclusion::class);
    }

    public function backupRuns(): HasMany
    {
        return $this->hasMany(BackupRun::class);
    }

    /** @return list<string> */
    public function effectiveExclusions(): array
    {
        $custom = $this->exclusionRules()->pluck('pattern')->all();

        return array_values(array_unique(array_merge(self::DEFAULT_EXCLUSIONS, $custom)));
    }

    public function sessionTable(): string
    {
        return $this->database?->sessionTable() ?? 'modx_session';
    }

    public function backupDumpPath(): string
    {
        return rtrim($this->root_path, '/').'/.backaper/db.sql';
    }

    public function syncExclusions(array $patterns): void
    {
        $patterns = array_values(array_filter(array_map('trim', $patterns)));

        $this->exclusionRules()->delete();

        foreach ($patterns as $pattern) {
            $this->exclusionRules()->create(['pattern' => $pattern]);
        }
    }
}
