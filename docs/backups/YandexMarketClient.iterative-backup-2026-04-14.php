<?php

namespace App\Integrations\YandexMarket;

use App\Services\Integrations\IntegrationSettingsService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class YandexMarketClient
{
    private ?int $resolvedBusinessId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settingsCache = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly IntegrationSettingsService $settingsService,
    ) {
    }

    public function isConfigured(): bool
    {
        $settings = $this->settings();

        return filled($settings['api_url'] ?? null)
            && filled($settings['token'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkConnection(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Интеграция с Яндекс Маркетом не настроена. Укажите токен в админке или на сервере.');
        }

        $campaigns = $this->fetchCampaigns();
        $businessId = (int) (
            Arr::get($campaigns, '0.business.id')
            ?: Arr::get($campaigns, '0.businessId')
            ?: ($this->settings()['business_id'] ?? 0)
        );

        $activeCampaignIds = $this->activeCampaignIds();

        return [
            'connected' => true,
            'business_id' => $businessId > 0 ? $businessId : null,
            'business_name' => Arr::get($campaigns, '0.business.name'),
            'active_campaign_ids' => $activeCampaignIds,
            'campaigns' => collect($campaigns)
                ->map(fn (array $campaign): array => [
                    'id' => Arr::get($campaign, 'id'),
                    'name' => Arr::get($campaign, 'domain', 'Без названия'),
                    'placement_type' => Arr::get($campaign, 'placementType'),
                    'api_availability' => Arr::get($campaign, 'apiAvailability'),
                    'is_active' => in_array((int) Arr::get($campaign, 'id'), $activeCampaignIds, true),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $pricesByOfferId
     * @return array<string, array<string, mixed>>
     */
    public function getOfferMetrics(array $offerIds, array $pricesByOfferId = []): array
    {
        $offerIds = array_values(array_filter(array_map(
            static fn (mixed $offerId): string => trim((string) $offerId),
            $offerIds,
        )));

        if ($offerIds === []) {
            return [];
        }

        $businessId = $this->resolveBusinessId();
        $mappings = collect();

        foreach (array_chunk(array_values(array_unique($offerIds)), 100) as $chunk) {
            $mappings = $mappings->merge($this->fetchOfferMappings($businessId, [
                'offerIds' => $chunk,
            ], count($chunk)));
        }

        $metrics = $mappings
            ->keyBy(fn (array $item): string => (string) data_get($item, 'offer.offerId'))
            ->map(function (array $item) use ($pricesByOfferId): array {
                $offerId = (string) data_get($item, 'offer.offerId');
                $price = data_get($item, 'offer.basicPrice.value');
                $currency = data_get($item, 'offer.basicPrice.currencyId');
                $initialPrice = data_get($pricesByOfferId, $offerId . '.initial_price');
                $initialPriceCurrency = data_get($pricesByOfferId, $offerId . '.initial_price_currency');
                $dimensions = data_get($item, 'offer.weightDimensions', []);
                $length = data_get($dimensions, 'length');
                $width = data_get($dimensions, 'width');
                $height = data_get($dimensions, 'height');
                $weight = data_get($dimensions, 'weight');
                $b2cUrl = Arr::get(
                    collect(data_get($item, 'showcaseUrls', []))
                        ->firstWhere('showcaseType', 'B2C') ?? [],
                    'showcaseUrl',
                );

                return [
                    'initial_price' => filled($initialPrice) ? (float) $initialPrice : null,
                    'initial_price_currency' => filled($initialPriceCurrency) ? (string) $initialPriceCurrency : null,
                    'market_price' => filled($price) ? (float) $price : null,
                    'market_price_currency' => filled($currency) ? (string) $currency : null,
                    'market_price_updated_at' => filled(data_get($item, 'offer.basicPrice.updatedAt'))
                        ? (string) data_get($item, 'offer.basicPrice.updatedAt')
                        : null,
                    'market_service_cost' => null,
                    'market_service_cost_currency' => null,
                    'market_service_cost_breakdown' => [],
                    'market_service_cost_note' => null,
                    'recommended_market_price' => null,
                    'recommended_market_price_currency' => null,
                    'recommended_market_net_payout' => null,
                    'recommended_market_price_note' => null,
                    'market_category_id' => filled(data_get($item, 'mapping.marketCategoryId'))
                        ? (int) data_get($item, 'mapping.marketCategoryId')
                        : null,
                    'market_sku' => filled(data_get($item, 'mapping.marketSku'))
                        ? (int) data_get($item, 'mapping.marketSku')
                        : null,
                    'market_url' => filled($b2cUrl)
                        ? (string) $b2cUrl
                        : (filled(data_get($item, 'showcaseUrls.0.showcaseUrl'))
                            ? (string) data_get($item, 'showcaseUrls.0.showcaseUrl')
                            : null),
                    '_dimensions' => [
                        'length' => self::positiveFloatOrDefault($length, 10.0),
                        'width' => self::positiveFloatOrDefault($width, 10.0),
                        'height' => self::positiveFloatOrDefault($height, 10.0),
                        'weight' => self::positiveFloatOrDefault($weight, 0.5),
                        'has_real_dimensions' => self::hasPositiveDimensions($length, $width, $height, $weight),
                    ],
                ];
            })
            ->all();

        try {
            $recommendedByOfferId = $this->calculateRecommendedPrices($metrics);
        } catch (\Throwable) {
            $recommendedByOfferId = [];
        }

        $metricsWithoutRecommendedPrice = array_filter(
            $metrics,
            static fn (array $metric, string $offerId): bool => ! array_key_exists($offerId, $recommendedByOfferId),
            ARRAY_FILTER_USE_BOTH,
        );

        try {
            $tariffsByOfferId = $this->calculateTariffs($metricsWithoutRecommendedPrice);
        } catch (\Throwable) {
            $tariffsByOfferId = [];
        }

        foreach ($metrics as $offerId => &$metric) {
            $recommended = $recommendedByOfferId[$offerId] ?? null;

            if ($recommended !== null) {
                $metric['market_service_cost'] = $recommended['market_service_cost'];
                $metric['market_service_cost_currency'] = $recommended['market_service_cost_currency'];
                $metric['market_service_cost_breakdown'] = $recommended['market_service_cost_breakdown'];
                $metric['market_service_cost_note'] = $recommended['market_service_cost_note'];
                $metric['recommended_market_price'] = $recommended['recommended_market_price'];
                $metric['recommended_market_price_currency'] = $recommended['recommended_market_price_currency'];
                $metric['recommended_market_net_payout'] = $recommended['recommended_market_net_payout'];
                $metric['recommended_market_price_note'] = $recommended['recommended_market_price_note'];
            } else {
                $tariff = $tariffsByOfferId[$offerId] ?? null;

                if ($tariff !== null) {
                    $metric['market_service_cost'] = $tariff['market_service_cost'];
                    $metric['market_service_cost_currency'] = $tariff['market_service_cost_currency'];
                    $metric['market_service_cost_breakdown'] = $tariff['market_service_cost_breakdown'];
                    $metric['market_service_cost_note'] = $tariff['market_service_cost_note'];
                }
            }

            unset($metric['_dimensions']);
        }
        unset($metric);

        return $metrics;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchProducts(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $businessId = $this->resolveBusinessId();
        $matches = collect();

        $exactMatches = $this->fetchOfferMappings($businessId, [
            'offerIds' => [$query],
        ]);

        if ($exactMatches->isNotEmpty()) {
            $matches = $matches->merge($exactMatches);
        }

        $pageToken = null;
        $attempts = 0;

        while ($matches->count() < $limit && $attempts < 3) {
            $result = $this->fetchOfferMappingsPage(
                $businessId,
                ['archived' => false],
                200,
                $pageToken,
            );

            $pageMatches = collect(Arr::get($result, 'offerMappings', []))
                ->filter(fn (array $item): bool => $this->matchesQuery($item, $query));

            $matches = $matches->merge($pageMatches);

            $pageToken = Arr::get($result, 'paging.nextPageToken');

            if (! filled($pageToken)) {
                break;
            }

            $attempts++;
        }

        return $matches
            ->unique(fn (array $item): string => (string) data_get($item, 'offer.offerId'))
            ->map(fn (array $item): array => $this->mapOffer($item))
            ->take($limit)
            ->values()
            ->all();
    }

    protected function resolveBusinessId(): int
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Интеграция с Яндекс Маркетом не настроена. Укажите токен в админке или на сервере.');
        }

        if ($this->resolvedBusinessId !== null) {
            return $this->resolvedBusinessId;
        }

        $configuredBusinessId = $this->settings()['business_id'] ?? null;

        if (filled($configuredBusinessId)) {
            return $this->resolvedBusinessId = (int) $configuredBusinessId;
        }

        $campaigns = $this->fetchCampaigns();
        $businessId = (int) (
            Arr::get($campaigns, '0.business.id')
            ?: Arr::get($campaigns, '0.businessId')
            ?: Arr::get($campaigns, '0.id')
        );

        if ($businessId < 1) {
            throw new RuntimeException('Не удалось определить businessId для кабинета Яндекс Маркета.');
        }

        return $this->resolvedBusinessId = $businessId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchCampaigns(): array
    {
        $response = $this->client()
            ->get('/v2/campaigns')
            ->throw()
            ->json();

        return Arr::get($response, 'result.campaigns', Arr::get($response, 'campaigns', []));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchOfferMappings(int $businessId, array $body = [], int $limit = 50, ?string $pageToken = null): Collection
    {
        return collect(Arr::get(
            $this->fetchOfferMappingsPage($businessId, $body, $limit, $pageToken),
            'offerMappings',
            [],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchOfferMappingsPage(int $businessId, array $body = [], int $limit = 50, ?string $pageToken = null): array
    {
        $query = array_filter([
            'limit' => min(max($limit, 1), 200),
            'language' => 'RU',
            'pageToken' => $pageToken,
        ], static fn (mixed $value): bool => filled($value));

        $response = $this->client()
            ->post("/v2/businesses/{$businessId}/offer-mappings?" . http_build_query($query), $body)
            ->throw()
            ->json();

        return Arr::get($response, 'result', $response);
    }

    protected function matchesQuery(array $item, string $query): bool
    {
        $needle = Str::lower($query);
        $haystack = Str::lower(implode(' ', array_filter([
            (string) data_get($item, 'offer.name', ''),
            (string) data_get($item, 'offer.offerId', ''),
            (string) data_get($item, 'offer.vendorCode', ''),
            (string) data_get($item, 'offer.vendor', ''),
            (string) data_get($item, 'offer.category', ''),
            (string) data_get($item, 'mapping.marketSku', ''),
        ])));

        return Str::contains($haystack, $needle);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapOffer(array $item): array
    {
        $marketCategoryId = data_get($item, 'offer.marketCategoryId');

        return [
            'name' => (string) data_get($item, 'offer.name', 'Без названия'),
            'offer_id' => (string) data_get($item, 'offer.offerId', ''),
            'sku' => data_get($item, 'offer.vendorCode')
                ? (string) data_get($item, 'offer.vendorCode')
                : (filled(data_get($item, 'mapping.marketSku')) ? (string) data_get($item, 'mapping.marketSku') : null),
            'category' => data_get($item, 'offer.category')
                ? (string) data_get($item, 'offer.category')
                : (filled($marketCategoryId) ? 'Категория #' . $marketCategoryId : null),
            'vendor' => data_get($item, 'offer.vendor') ? (string) data_get($item, 'offer.vendor') : null,
            'status' => data_get($item, 'offer.campaigns.0.status', 'UNKNOWN'),
            'market_sku' => filled(data_get($item, 'mapping.marketSku'))
                ? (string) data_get($item, 'mapping.marketSku')
                : null,
            'campaign_ids' => $this->activeCampaignIds(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  array<string, float>  $priceOverrides
     * @return array<string, array<string, mixed>>
     */
    protected function calculateTariffs(array $metrics, array $priceOverrides = []): array
    {
        $offers = [];
        $offerIds = [];

        foreach ($metrics as $offerId => $metric) {
            $categoryId = (int) ($metric['market_category_id'] ?? 0);
            $price = (float) ($priceOverrides[$offerId] ?? $metric['initial_price'] ?? $metric['market_price'] ?? 0);
            $dimensions = $metric['_dimensions'] ?? [];

            if ($categoryId < 1 || $price <= 0) {
                continue;
            }

            $offers[] = [
                'categoryId' => $categoryId,
                'price' => $price,
                'length' => self::positiveFloatOrDefault($dimensions['length'] ?? null, 10.0),
                'width' => self::positiveFloatOrDefault($dimensions['width'] ?? null, 10.0),
                'height' => self::positiveFloatOrDefault($dimensions['height'] ?? null, 10.0),
                'weight' => self::positiveFloatOrDefault($dimensions['weight'] ?? null, 0.5),
            ];
            $offerIds[] = $offerId;
        }

        if ($offers === []) {
            return [];
        }

        $campaignId = $this->activeCampaignIds()[0] ?? null;

        $parameters = filled($campaignId)
            ? ['campaignId' => $campaignId]
            : [
                'sellingProgram' => 'FBS',
                'currency' => 'RUR',
            ];

        $response = $this->client()
            ->post('/v2/tariffs/calculate', [
                'offers' => $offers,
                'parameters' => $parameters,
            ])
            ->throw()
            ->json();

        $result = [];

        foreach (Arr::get($response, 'result.offers', []) as $index => $offerInfo) {
            $offerId = $offerIds[$index] ?? null;

            if ($offerId === null) {
                continue;
            }

            $tariffs = collect(Arr::get($offerInfo, 'tariffs', []))
                ->map(fn (array $tariff): array => [
                    'type' => (string) Arr::get($tariff, 'type'),
                    'amount' => filled(Arr::get($tariff, 'amount')) ? (float) Arr::get($tariff, 'amount') : 0.0,
                    'currency' => filled(Arr::get($tariff, 'currency')) ? (string) Arr::get($tariff, 'currency') : 'RUR',
                ])
                ->values();

            $hasRealDimensions = (bool) data_get($metrics, $offerId . '._dimensions.has_real_dimensions', false);
            $usesInitialPrice = filled(data_get($metrics, $offerId . '.initial_price'));
            $noteBase = $usesInitialPrice
                ? 'Оценка рассчитана по тарифам API Маркета на основе цены продажи из ERP МойСклад'
                : 'Оценка рассчитана по API Маркета';

            $result[$offerId] = [
                'market_service_cost' => $tariffs->sum('amount'),
                'market_service_cost_currency' => (string) ($tariffs->first()['currency'] ?? 'RUR'),
                'market_service_cost_breakdown' => $tariffs->all(),
                'market_service_cost_note' => $hasRealDimensions
                    ? $noteBase . '.'
                    : $noteBase . ' с базовыми габаритами товара.',
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @return array<string, array<string, mixed>>
     */
    protected function calculateRecommendedPrices(array $metrics): array
    {
        $candidatePrices = [];

        foreach ($metrics as $offerId => $metric) {
            $initialPrice = (float) ($metric['initial_price'] ?? 0);
            $categoryId = (int) ($metric['market_category_id'] ?? 0);

            if ($initialPrice <= 0 || $categoryId < 1) {
                continue;
            }

            $candidatePrices[$offerId] = round($initialPrice, 2);
        }

        if ($candidatePrices === []) {
            return [];
        }

        $maxIterations = 6;

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $tariffs = $this->calculateTariffs($metrics, $candidatePrices);
            $hasChanges = false;

            foreach ($candidatePrices as $offerId => $candidatePrice) {
                $initialPrice = (float) data_get($metrics, $offerId . '.initial_price', 0);
                $serviceCost = (float) data_get($tariffs, $offerId . '.market_service_cost', 0);
                $nextPrice = round($initialPrice + $serviceCost, 2);

                if (abs($nextPrice - $candidatePrice) >= 0.01) {
                    $candidatePrices[$offerId] = $nextPrice;
                    $hasChanges = true;
                }
            }

            if (! $hasChanges) {
                break;
            }
        }

        $finalTariffs = $this->calculateTariffs($metrics, $candidatePrices);
        $result = [];

        foreach ($candidatePrices as $offerId => $candidatePrice) {
            $initialPrice = round((float) data_get($metrics, $offerId . '.initial_price', 0), 2);
            $serviceCost = round((float) data_get($finalTariffs, $offerId . '.market_service_cost', 0), 2);
            $finalRecommendedPrice = round($initialPrice + $serviceCost, 2);
            $currency = (string) (
                data_get($finalTariffs, $offerId . '.market_service_cost_currency')
                ?: data_get($metrics, $offerId . '.initial_price_currency')
                ?: data_get($metrics, $offerId . '.market_price_currency')
                ?: 'RUR'
            );
            $hasRealDimensions = (bool) data_get($metrics, $offerId . '._dimensions.has_real_dimensions', false);
            $serviceCostBreakdown = data_get($finalTariffs, $offerId . '.market_service_cost_breakdown', []);
            $serviceCostNote = $hasRealDimensions
                ? 'Комиссии рассчитаны по API Маркета для рекомендованной цены продажи.'
                : 'Комиссии рассчитаны по API Маркета для рекомендованной цены продажи с базовыми габаритами товара.';

            $result[$offerId] = [
                'market_service_cost' => $serviceCost,
                'market_service_cost_currency' => $currency,
                'market_service_cost_breakdown' => is_array($serviceCostBreakdown) ? $serviceCostBreakdown : [],
                'market_service_cost_note' => $serviceCostNote,
                'recommended_market_price' => $finalRecommendedPrice,
                'recommended_market_price_currency' => $currency,
                'recommended_market_net_payout' => round($finalRecommendedPrice - $serviceCost, 2),
                'recommended_market_price_note' => 'Цена подобрана итерационно: после комиссий Маркета выплата остаётся на уровне начальной цены.',
            ];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    protected function activeCampaignIds(): array
    {
        $campaignIds = $this->settings()['campaign_ids'] ?? [];

        return is_array($campaignIds)
            ? array_values(array_filter(array_map('intval', $campaignIds), static fn (int $id): bool => $id > 0))
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        return $this->settingsCache ??= $this->settingsService->getYandexMarketSettings();
    }

    protected static function positiveFloatOrDefault(mixed $value, float $default): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $normalized = (float) $value;

        return $normalized > 0 ? $normalized : $default;
    }

    protected static function hasPositiveDimensions(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if (! is_numeric($value) || (float) $value <= 0) {
                return false;
            }
        }

        return true;
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        $settings = $this->settings();

        return $this->http
            ->baseUrl(rtrim((string) ($settings['api_url'] ?? ''), '/'))
            ->acceptJson()
            ->withHeaders([
                'Api-Key' => (string) ($settings['token'] ?? ''),
            ])
            ->timeout(20)
            ->connectTimeout(10);
    }
}
