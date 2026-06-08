<?php

namespace App\Models;

use App\Services\BackupLogParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $fillable = [
        'server_id',
        'project_id',
        'status',
        'remote_pid',
        'log',
        'started_at',
        'finished_at',
    ];

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /** @return array<string, mixed> */
    public function parsedLog(): array
    {
        return app(BackupLogParser::class)->parse($this->log);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
