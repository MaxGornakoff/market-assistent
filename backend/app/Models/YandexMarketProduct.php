<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YandexMarketProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'offer_id',
        'sku',
        'category',
        'monitoring_enabled',
        'campaign_ids',
        'created_by',
    ];

    protected $casts = [
        'monitoring_enabled' => 'boolean',
        'campaign_ids' => 'array',
    ];
}
