<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupBatch extends Model
{
    public const MODE_FILES = 'files';

    public const MODE_DATABASES = 'databases';

    public const MODE_BOTH = 'both';

    protected $fillable = [
        'status',
        'mode',
        'poll_seconds',
        'current_item_id',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'poll_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BackupBatchItem::class)->orderBy('position');
    }

    public function currentItem(): BelongsTo
    {
        return $this->belongsTo(BackupBatchItem::class, 'current_item_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    /** @return array{files: bool, databases: bool} */
    public function modeOptions(): array
    {
        return match ($this->mode) {
            self::MODE_FILES => ['files' => true, 'databases' => false],
            self::MODE_DATABASES => ['files' => false, 'databases' => true],
            default => ['files' => true, 'databases' => true],
        };
    }

    public function modeLabel(): string
    {
        return match ($this->mode) {
            self::MODE_FILES => 'Только файлы',
            self::MODE_DATABASES => 'Только базы',
            default => 'Файлы + базы',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'В очереди',
            'running' => 'Идёт',
            'completed' => 'Готово',
            'failed' => 'Ошибка',
            'cancelled' => 'Отменён',
            default => $this->status,
        };
    }
}
