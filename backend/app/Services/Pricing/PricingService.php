<?php

namespace App\Services\Pricing;

use App\Integrations\MoySklad\MoySkladClient;
use App\Integrations\YandexMarket\YandexMarketClient;

class PricingService
{
    public function __construct(
        private readonly MoySkladClient $moySkladClient,
        private readonly YandexMarketClient $yandexMarketClient,
    ) {
    }

    public function getDashboardSummary(): array
    {
        return [
            'status' => 'ready',
            'message' => 'Initial pricing assistant structure is ready.',
            'modules' => [
                'dashboard',
                'pricing-rules',
                'moysklad-sync',
                'yandex-market-sync',
                'analytics',
            ],
            'integrations' => [
                'moysklad' => [
                    'configured' => $this->moySkladClient->isConfigured(),
                ],
                'yandex_market' => [
                    'configured' => $this->yandexMarketClient->isConfigured(),
                ],
            ],
        ];
    }
}
