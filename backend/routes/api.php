<?php

use App\Http\Controllers\Api\Admin\IntegrationSettingsController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\YandexMarketProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::middleware('web')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard-summary', [PricingController::class, 'summary']);

        Route::prefix('yandex-market')->group(function (): void {
            Route::get('/catalog/search', [YandexMarketProductController::class, 'searchCatalog']);
            Route::get('/products', [YandexMarketProductController::class, 'index']);
            Route::post('/products', [YandexMarketProductController::class, 'store']);
            Route::patch('/products/{product}', [YandexMarketProductController::class, 'update']);
            Route::delete('/products/{product}', [YandexMarketProductController::class, 'destroy']);
        });

        Route::middleware('admin')->prefix('admin')->group(function (): void {
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);

            Route::prefix('integrations')->group(function (): void {
                Route::get('/yandex-market', [IntegrationSettingsController::class, 'showYandexMarket']);
                Route::put('/yandex-market', [IntegrationSettingsController::class, 'updateYandexMarket']);
                Route::post('/yandex-market/check', [IntegrationSettingsController::class, 'checkYandexMarket']);
            });
        });

        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
