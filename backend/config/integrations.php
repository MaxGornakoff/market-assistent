<?php

return [
    'moysklad' => [
        'base_url' => env('MOYSKLAD_BASE_URL', 'https://api.moysklad.ru/api/remap/1.2'),
        'token' => env('MOYSKLAD_TOKEN'),
    ],

    'yandex_market' => [
        'api_url' => env('YANDEX_MARKET_API_URL', 'https://api.partner.market.yandex.ru'),
        'business_id' => env('YANDEX_MARKET_BUSINESS_ID'),
        'campaign_id' => env('YANDEX_MARKET_CAMPAIGN_ID'),
        'campaign_ids' => env('YANDEX_MARKET_CAMPAIGN_IDS'),
        'token' => env('YANDEX_MARKET_TOKEN'),
    ],
];
