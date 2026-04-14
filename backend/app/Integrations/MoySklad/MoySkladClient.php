<?php

namespace App\Integrations\MoySklad;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;

class MoySkladClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled(config('integrations.moysklad.base_url'))
            && filled(config('integrations.moysklad.token'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSalePricesByCodes(array $codes, string $priceTypeName = 'Цена продажи'): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $normalizedCodes = array_values(array_filter(array_unique(array_map(
            static fn (mixed $code): string => trim((string) $code),
            $codes,
        ))));

        $pricesByCode = [];

        foreach ($normalizedCodes as $code) {
            $entity = $this->fetchProductByCode($code);
            $price = $entity !== null ? $this->extractSalePrice($entity, $priceTypeName) : null;

            if ($price === null) {
                $variant = $this->fetchVariantByCode($code);
                $price = $variant !== null ? $this->extractSalePrice($variant, $priceTypeName) : null;
            }

            if ($price !== null) {
                $pricesByCode[$code] = $price;
            }
        }

        return $pricesByCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchProductByCode(string $code): ?array
    {
        return $this->fetchEntityByCode('/entity/product', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchVariantByCode(string $code): ?array
    {
        return $this->fetchEntityByCode('/entity/variant', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchEntityByCode(string $endpoint, string $code): ?array
    {
        $response = $this->client()
            ->get($endpoint, [
                'filter' => 'code=' . $code,
                'limit' => 1,
            ])
            ->throw()
            ->json();

        $entity = Arr::get($response, 'rows.0');

        return is_array($entity) ? $entity : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractSalePrice(array $product, string $priceTypeName): ?array
    {
        $salePrices = collect(Arr::get($product, 'salePrices', []));

        $matchedPrice = $salePrices->first(
            fn (array $price): bool => trim((string) Arr::get($price, 'priceType.name')) === $priceTypeName,
        );

        if (! is_array($matchedPrice)) {
            $matchedPrice = $salePrices->first();
        }

        if (! is_array($matchedPrice) || ! filled(Arr::get($matchedPrice, 'value'))) {
            return null;
        }

        return [
            'initial_price' => $this->normalizeMoneyValue(Arr::get($matchedPrice, 'value')),
            'initial_price_currency' => (string) (Arr::get($matchedPrice, 'currency.isoCode') ?: 'RUB'),
        ];
    }

    protected function normalizeMoneyValue(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (float) $value;

        if ($normalized <= 0) {
            return null;
        }

        return fmod($normalized, 1.0) === 0.0
            ? round($normalized / 100, 2)
            : round($normalized, 2);
    }

    protected function client(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim((string) config('integrations.moysklad.base_url'), '/'))
            ->withHeaders([
                'Authorization' => 'Bearer ' . (string) config('integrations.moysklad.token'),
                'Accept' => 'application/json;charset=utf-8',
                'Content-Type' => 'application/json;charset=utf-8',
                'Accept-Encoding' => 'gzip',
            ])
            ->timeout(20)
            ->connectTimeout(10);
    }
}
