<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupPath extends Model
{
    protected $fillable = [
        'server_id',
        'path',
        'label',
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

    public function displayName(): string
    {
        return $this->label ?: basename(rtrim($this->path, '/')) ?: $this->path;
    }
}
