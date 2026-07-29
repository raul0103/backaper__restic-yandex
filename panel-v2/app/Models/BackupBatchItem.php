<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupBatchItem extends Model
{
    protected $fillable = [
        'backup_batch_id',
        'server_id',
        'position',
        'status',
        'backup_run_id',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BackupBatch::class, 'backup_batch_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'skipped'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Ждёт',
            'running' => 'Бэкап…',
            'completed' => 'Готово',
            'failed' => 'Ошибка',
            'skipped' => 'Пропуск',
            default => $this->status,
        };
    }
}
