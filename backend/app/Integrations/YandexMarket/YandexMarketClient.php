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
