<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Schema;

class IntegrationSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function getYandexMarketSettings(): array
    {
        $defaultCampaignIds = $this->normalizeIntegerArray(config('integrations.yandex_market.campaign_ids'));

        if ($defaultCampaignIds === []) {
            $legacyCampaignId = $this->normalizeInteger(config('integrations.yandex_market.campaign_id'));
            $defaultCampaignIds = $legacyCampaignId ? [$legacyCampaignId] : [];
        }

        $defaults = [
            'api_url' => (string) config('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru'),
            'business_id' => $this->normalizeInteger(config('integrations.yandex_market.business_id')),
            'campaign_ids' => $defaultCampaignIds,
            'token' => $this->normalizeString(config('integrations.yandex_market.token')),
        ];

        if (! Schema::hasTable('integration_settings')) {
            return [
                ...$defaults,
                'campaign_id' => $defaults['campaign_ids'][0] ?? null,
            ];
        }

        $stored = $this->getStoredSettings('yandex_market');
        $storedCampaignIds = $this->normalizeIntegerArray($stored['campaign_ids'] ?? null);

        if ($storedCampaignIds === []) {
            $legacyStoredCampaignId = $this->normalizeInteger($stored['campaign_id'] ?? null);
            $storedCampaignIds = $legacyStoredCampaignId ? [$legacyStoredCampaignId] : [];
        }

        $campaignIds = $storedCampaignIds !== [] ? $storedCampaignIds : $defaults['campaign_ids'];

        return [
            'api_url' => $this->normalizeString($stored['api_url'] ?? null) ?? $defaults['api_url'],
            'business_id' => $this->normalizeInteger($stored['business_id'] ?? null) ?? $defaults['business_id'],
            'campaign_ids' => $campaignIds,
            'campaign_id' => $campaignIds[0] ?? null,
            'token' => $this->normalizeString($stored['token'] ?? null) ?? $defaults['token'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getYandexMarketAdminPayload(): array
    {
        $settings = $this->getYandexMarketSettings();
        $token = $settings['token'] ?? null;

        return [
            'api_url' => $settings['api_url'],
            'business_id' => $settings['business_id'],
            'campaign_ids' => $settings['campaign_ids'],
            'campaign_id' => $settings['campaign_id'],
            'has_token' => filled($token),
            'token_masked' => $this->maskToken($token),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateYandexMarketSettings(array $payload): array
    {
        $settings = $this->getYandexMarketSettings();

        if (array_key_exists('api_url', $payload)) {
            $settings['api_url'] = $this->normalizeString($payload['api_url']) ?? $settings['api_url'];
        }

        if (array_key_exists('business_id', $payload)) {
            $settings['business_id'] = $this->normalizeInteger($payload['business_id']);
        }

        if (array_key_exists('campaign_ids', $payload)) {
            $settings['campaign_ids'] = $this->normalizeIntegerArray($payload['campaign_ids']);
        } elseif (array_key_exists('campaign_id', $payload)) {
            $legacyCampaignId = $this->normalizeInteger($payload['campaign_id']);
            $settings['campaign_ids'] = $legacyCampaignId ? [$legacyCampaignId] : [];
        }

        if (($payload['clear_token'] ?? false) === true) {
            $settings['token'] = null;
        } elseif (array_key_exists('token', $payload) && filled($payload['token'])) {
            $settings['token'] = trim((string) $payload['token']);
        }

        IntegrationSetting::query()->updateOrCreate(
            ['key' => 'yandex_market'],
            ['settings' => [
                'api_url' => $settings['api_url'],
                'business_id' => $settings['business_id'],
                'campaign_ids' => $settings['campaign_ids'],
                'campaign_id' => $settings['campaign_ids'][0] ?? null,
                'token' => $settings['token'],
            ]],
        );

        return $this->getYandexMarketAdminPayload();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getStoredSettings(string $key): array
    {
        $record = IntegrationSetting::query()
            ->where('key', $key)
            ->first();

        return is_array($record?->settings) ? $record->settings : [];
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeIntegerArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value)
            ? $value
            : preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn (mixed $item): int => (int) $item)
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function maskToken(?string $token): ?string
    {
        if (! filled($token)) {
            return null;
        }

        $length = mb_strlen($token);

        if ($length <= 10) {
            return str_repeat('•', $length);
        }

        return mb_substr($token, 0, 6) . '…' . mb_substr($token, -4);
    }
}
