<?php

namespace App\Integrations\YandexMarket;

class YandexMarketClient
{
    public function isConfigured(): bool
    {
        return filled(config('integrations.yandex_market.api_url'))
            && filled(config('integrations.yandex_market.campaign_id'))
            && filled(config('integrations.yandex_market.token'));
    }
}
