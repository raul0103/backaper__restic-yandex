<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDatabase extends Model
{
    protected $fillable = [
        'server_id',
        'modx_config_id',
        'database_server',
        'database_name',
        'database_user',
        'database_password',
        'table_prefix',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function modxConfig(): BelongsTo
    {
        return $this->belongsTo(ModxConfig::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sessionTable(): string
    {
        return $this->table_prefix.'session';
    }
}
