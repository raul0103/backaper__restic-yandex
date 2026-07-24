<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseCredential extends Model
{
    protected $table = 'databases';

    protected $fillable = [
        'server_id',
        'label',
        'source',
        'config_path',
        'database_server',
        'database_name',
        'database_user',
        'database_password',
        'table_prefix',
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
        return $this->label ?: $this->database_name;
    }
}
