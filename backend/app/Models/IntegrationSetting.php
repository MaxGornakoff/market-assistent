<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'settings',
    ];

    protected $casts = [
        'settings' => 'encrypted:array',
    ];
}
