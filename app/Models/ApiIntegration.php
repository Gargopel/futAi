<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIntegration extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'is_active',
        'config',
        'last_synced_at',
        'last_sync_summary',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'config' => 'array',
        'last_synced_at' => 'datetime',
        'last_sync_summary' => 'array',
    ];
}
