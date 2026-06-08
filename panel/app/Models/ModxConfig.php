<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModxConfig extends Model
{
    protected $fillable = [
        'server_id',
        'config_path',
        'suggested_root_path',
        'label',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function database(): HasOne
    {
        return $this->hasOne(ProjectDatabase::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
