<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\YandexMarket\YandexMarketClient;
use App\Models\YandexMarketProduct;
use App\Services\Integrations\IntegrationSettingsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class YandexMarketProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = YandexMarketProduct::query()
            ->latest('id')
            ->get([
                'id',
                'name',
                'offer_id',
                'sku',
                'category',
                'status',
                'monitoring_enabled',
                'campaign_ids',
                'created_at',
            ]);

        return response()->json([
            'products' => $products,
        ]);
    }

    public function searchCatalog(Request $request, YandexMarketClient $client): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        try {
            $products = $client->searchProducts($validated['query']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось выполнить поиск в каталоге Яндекс Маркета. Проверь токен и доступ к Partner API.',
            ], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'products' => $products,
        ]);
    }

    public function store(Request $request, IntegrationSettingsService $settingsService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'offer_id' => ['required', 'string', 'max:120', 'unique:yandex_market_products,offer_id'],
            'sku' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:255'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => ['integer', 'min:1'],
        ]);

        $configuredCampaignIds = $settingsService->getYandexMarketSettings()['campaign_ids'] ?? [];
        $campaignIds = collect($validated['campaign_ids'] ?? $configuredCampaignIds)
            ->map(fn (mixed $item): int => (int) $item)
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();

        $product = YandexMarketProduct::query()->create([
            'name' => $validated['name'],
            'offer_id' => $validated['offer_id'],
            'sku' => $validated['sku'] ?? null,
            'category' => $validated['category'] ?? null,
            'status' => 'draft',
            'monitoring_enabled' => $validated['monitoring_enabled'] ?? true,
            'campaign_ids' => $campaignIds,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Товар добавлен в таблицу.',
            'product' => $product,
        ], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, YandexMarketProduct $product): JsonResponse
    {
        $validated = $request->validate([
            'monitoring_enabled' => ['required', 'boolean'],
        ]);

        $product->forceFill([
            'monitoring_enabled' => $validated['monitoring_enabled'],
        ])->save();

        return response()->json([
            'message' => $product->monitoring_enabled
                ? 'Отслеживание товара включено.'
                : 'Отслеживание товара отключено.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(YandexMarketProduct $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Товар удалён из таблицы мониторинга.',
        ]);
    }
}
