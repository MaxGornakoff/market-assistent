<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\YandexMarket\YandexMarketClient;
use App\Services\Integrations\IntegrationSettingsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IntegrationSettingsController extends Controller
{
    public function showYandexMarket(IntegrationSettingsService $settingsService): JsonResponse
    {
        return response()->json([
            'settings' => $settingsService->getYandexMarketAdminPayload(),
        ]);
    }

    public function updateYandexMarket(Request $request, IntegrationSettingsService $settingsService): JsonResponse
    {
        $validated = $request->validate([
            'api_url' => ['nullable', 'url', 'max:255'],
            'business_id' => ['nullable', 'integer', 'min:1'],
            'campaign_id' => ['nullable', 'integer', 'min:1'],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => ['integer', 'min:1'],
            'token' => ['nullable', 'string', 'max:500'],
            'clear_token' => ['sometimes', 'boolean'],
        ]);

        $settings = $settingsService->updateYandexMarketSettings($validated);

        return response()->json([
            'message' => 'Настройки интеграции сохранены.',
            'settings' => $settings,
        ]);
    }

    public function checkYandexMarket(YandexMarketClient $client): JsonResponse
    {
        try {
            $connection = $client->checkConnection();
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'connection' => [
                    'connected' => false,
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось подключиться к Яндекс Маркету. Проверь токен и права доступа.',
                'connection' => [
                    'connected' => false,
                ],
            ], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'message' => 'Подключение к Яндекс Маркету подтверждено.',
            'connection' => $connection,
        ]);
    }
}
