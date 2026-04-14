<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMarketProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_yandex_market_product(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        config()->set('integrations.yandex_market.campaign_ids', [68023107, 100655646]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/yandex-market/products', [
                'name' => 'Витамин D3 2000 IU',
                'offer_id' => 'vitamin-d3-2000',
                'sku' => 'SKU-1001',
                'category' => 'Витамины',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('product.name', 'Витамин D3 2000 IU')
            ->assertJsonPath('product.offer_id', 'vitamin-d3-2000')
            ->assertJsonPath('product.campaign_ids.0', 68023107)
            ->assertJsonPath('product.campaign_ids.1', 100655646);

        $this->assertDatabaseHas('yandex_market_products', [
            'name' => 'Витамин D3 2000 IU',
            'offer_id' => 'vitamin-d3-2000',
            'created_by' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_view_yandex_market_products(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Омега 3',
            'offer_id' => 'omega-3',
            'sku' => 'SKU-2002',
            'category' => 'БАДы',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107, 100655646], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.offer_id', 'omega-3')
            ->assertJsonPath('products.0.campaign_ids.0', 68023107)
            ->assertJsonPath('products.0.campaign_ids.1', 100655646);
    }

    public function test_authenticated_user_can_toggle_yandex_market_product_monitoring(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $productId = DB::table('yandex_market_products')->insertGetId([
            'name' => 'Магний B6',
            'offer_id' => 'magnesium-b6',
            'sku' => 'SKU-3003',
            'category' => 'Витамины',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107, 100655646], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson("/api/yandex-market/products/{$productId}", [
                'monitoring_enabled' => false,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('product.id', $productId)
            ->assertJsonPath('product.monitoring_enabled', false);

        $this->assertDatabaseHas('yandex_market_products', [
            'id' => $productId,
            'monitoring_enabled' => false,
        ]);
    }

    public function test_authenticated_user_can_delete_yandex_market_product(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $productId = DB::table('yandex_market_products')->insertGetId([
            'name' => 'Коэнзим Q10',
            'offer_id' => 'coenzyme-q10',
            'sku' => 'SKU-4004',
            'category' => 'БАДы',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/yandex-market/products/{$productId}");

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Товар удалён из таблицы мониторинга.');

        $this->assertDatabaseMissing('yandex_market_products', [
            'id' => $productId,
        ]);
    }

    public function test_authenticated_user_can_view_market_price_and_service_cost_for_tracked_products(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', 987654);
        config()->set('integrations.yandex_market.campaign_ids', [68023107]);

        Http::fake([
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'offerId' => 'omega-3',
                                'name' => 'Омега 3',
                                'basicPrice' => [
                                    'value' => 5105,
                                    'currencyId' => 'RUR',
                                    'updatedAt' => '2026-04-10T12:30:00+03:00',
                                ],
                                'weightDimensions' => [
                                    'length' => 12,
                                    'width' => 8,
                                    'height' => 18,
                                    'weight' => 0.4,
                                ],
                                'campaigns' => [
                                    [
                                        'campaignId' => 68023107,
                                        'status' => 'PUBLISHED',
                                    ],
                                ],
                            ],
                            'mapping' => [
                                'marketSku' => 123456,
                                'marketCategoryId' => 14247341,
                            ],
                            'showcaseUrls' => [
                                [
                                    'showcaseType' => 'B2C',
                                    'showcaseUrl' => 'https://market.yandex.ru/product--omega-3/123456',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/tariffs/calculate' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offers' => [
                        [
                            'offer' => [
                                'categoryId' => 14247341,
                                'price' => 5105,
                                'weight' => 0.4,
                                'length' => 12,
                                'width' => 8,
                                'height' => 18,
                            ],
                            'tariffs' => [
                                [
                                    'type' => 'FEE',
                                    'amount' => 320,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                                [
                                    'type' => 'DELIVERY_TO_CUSTOMER',
                                    'amount' => 135,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Омега 3',
            'offer_id' => 'omega-3',
            'sku' => 'SKU-2002',
            'category' => 'БАДы',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.offer_id', 'omega-3')
            ->assertJsonPath('products.0.market_price', 5105)
            ->assertJsonPath('products.0.market_price_currency', 'RUR')
            ->assertJsonPath('products.0.market_service_cost', 455)
            ->assertJsonPath('products.0.market_service_cost_breakdown.0.type', 'FEE');
    }

    public function test_market_service_cost_uses_moysklad_sale_price_linked_by_sku_code(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', 987654);
        config()->set('integrations.yandex_market.campaign_ids', [68023107]);
        config()->set('integrations.moysklad.base_url', 'https://api.moysklad.ru/api/remap/1.2');
        config()->set('integrations.moysklad.token', 'test-moysklad-token');

        Http::fake([
            'https://api.moysklad.ru/api/remap/1.2/entity/product*' => Http::response([
                'rows' => [
                    [
                        'name' => 'Maxler Creatine 1000, 100 капс.',
                        'code' => '899',
                        'salePrices' => [
                            [
                                'value' => 169000,
                                'currency' => [
                                    'isoCode' => 'RUB',
                                ],
                                'priceType' => [
                                    'name' => 'Цена продажи',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'offerId' => '899',
                                'name' => 'Maxler Creatine 1000, 100 капс.',
                                'basicPrice' => [
                                    'value' => 1890,
                                    'currencyId' => 'RUR',
                                    'updatedAt' => '2026-04-10T12:30:00+03:00',
                                ],
                                'weightDimensions' => [
                                    'length' => 12,
                                    'width' => 8,
                                    'height' => 18,
                                    'weight' => 0.4,
                                ],
                            ],
                            'mapping' => [
                                'marketSku' => 123456,
                                'marketCategoryId' => 14247341,
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/tariffs/calculate' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offers' => [
                        [
                            'offer' => [
                                'categoryId' => 14247341,
                                'price' => 1690,
                                'weight' => 0.4,
                                'length' => 12,
                                'width' => 8,
                                'height' => 18,
                            ],
                            'tariffs' => [
                                [
                                    'type' => 'FEE',
                                    'amount' => 120,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Maxler Creatine 1000, 100 капс.',
            'offer_id' => '899',
            'sku' => '899',
            'category' => 'Спортивное питание',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.initial_price', 1690)
            ->assertJsonPath('products.0.initial_price_currency', 'RUB')
            ->assertJsonPath('products.0.market_service_cost', 120);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/tariffs/calculate')) {
                return false;
            }

            return data_get($request->data(), 'offers.0.price') === 1690.0;
        });
    }

    public function test_recommended_market_price_preserves_initial_price_after_market_commissions(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', 987654);
        config()->set('integrations.yandex_market.campaign_ids', [68023107]);
        config()->set('integrations.moysklad.base_url', 'https://api.moysklad.ru/api/remap/1.2');
        config()->set('integrations.moysklad.token', 'test-moysklad-token');

        Http::fake([
            'https://api.moysklad.ru/api/remap/1.2/entity/product*' => Http::response([
                'rows' => [
                    [
                        'name' => 'Break-even product',
                        'code' => 'break-even-1',
                        'salePrices' => [
                            [
                                'value' => 100000,
                                'currency' => [
                                    'isoCode' => 'RUB',
                                ],
                                'priceType' => [
                                    'name' => 'Цена продажи',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'offerId' => 'break-even-1',
                                'name' => 'Break-even product',
                                'basicPrice' => [
                                    'value' => 1000,
                                    'currencyId' => 'RUR',
                                    'updatedAt' => '2026-04-10T12:30:00+03:00',
                                ],
                                'weightDimensions' => [
                                    'length' => 12,
                                    'width' => 8,
                                    'height' => 18,
                                    'weight' => 0.4,
                                ],
                            ],
                            'mapping' => [
                                'marketSku' => 123456,
                                'marketCategoryId' => 14247341,
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/tariffs/calculate' => function (Request $request) {
                $offers = collect(data_get($request->data(), 'offers', []))
                    ->map(function (array $offer): array {
                        $price = (float) ($offer['price'] ?? 0);
                        $fee = round($price * 0.2, 2);

                        return [
                            'offer' => $offer,
                            'tariffs' => [
                                [
                                    'type' => 'FEE',
                                    'amount' => $fee,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                            ],
                        ];
                    })
                    ->values()
                    ->all();

                return Http::response([
                    'status' => 'OK',
                    'result' => [
                        'offers' => $offers,
                    ],
                ]);
            },
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Break-even product',
            'offer_id' => 'break-even-1',
            'sku' => 'break-even-1',
            'category' => 'Тестовая категория',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.initial_price', 1000)
            ->assertJsonPath('products.0.market_service_cost', 250)
            ->assertJsonPath('products.0.market_service_cost_has_all_real_data', true)
            ->assertJsonPath('products.0.market_service_cost_missing_data', [])
            ->assertJsonPath('products.0.recommended_market_price', 1250)
            ->assertJsonPath('products.0.recommended_market_net_payout', 1000);
    }

    public function test_moysklad_variant_sale_price_is_used_when_product_price_is_missing(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', 987654);
        config()->set('integrations.yandex_market.campaign_ids', [68023107]);
        config()->set('integrations.moysklad.base_url', 'https://api.moysklad.ru/api/remap/1.2');
        config()->set('integrations.moysklad.token', 'test-moysklad-token');

        Http::fake([
            'https://api.moysklad.ru/api/remap/1.2/entity/product*' => Http::response([
                'rows' => [],
            ]),
            'https://api.moysklad.ru/api/remap/1.2/entity/variant*' => Http::response([
                'rows' => [
                    [
                        'name' => 'Variant product',
                        'code' => 'variant-1',
                        'salePrices' => [
                            [
                                'value' => 150000,
                                'currency' => [
                                    'isoCode' => 'RUB',
                                ],
                                'priceType' => [
                                    'name' => 'Цена продажи',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'offerId' => 'variant-1',
                                'name' => 'Variant product',
                                'basicPrice' => [
                                    'value' => 1700,
                                    'currencyId' => 'RUR',
                                    'updatedAt' => '2026-04-10T12:30:00+03:00',
                                ],
                                'weightDimensions' => [
                                    'length' => 12,
                                    'width' => 8,
                                    'height' => 18,
                                    'weight' => 0.4,
                                ],
                            ],
                            'mapping' => [
                                'marketSku' => 123456,
                                'marketCategoryId' => 14247341,
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/tariffs/calculate' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offers' => [
                        [
                            'offer' => [
                                'categoryId' => 14247341,
                                'price' => 1500,
                                'weight' => 0.4,
                                'length' => 12,
                                'width' => 8,
                                'height' => 18,
                            ],
                            'tariffs' => [
                                [
                                    'type' => 'FEE',
                                    'amount' => 150,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Variant product',
            'offer_id' => 'variant-1',
            'sku' => 'variant-1',
            'category' => 'Тестовая категория',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.initial_price', 1500)
            ->assertJsonPath('products.0.initial_price_currency', 'RUB');
    }

    public function test_market_service_cost_uses_safe_default_dimensions_when_offer_dimensions_are_zero(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', 987654);
        config()->set('integrations.yandex_market.campaign_ids', [68023107]);

        Http::fake([
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'offerId' => 'zero-dimensions',
                                'name' => 'Тестовый товар',
                                'basicPrice' => [
                                    'value' => 1999,
                                    'currencyId' => 'RUR',
                                    'updatedAt' => '2026-04-10T12:30:00+03:00',
                                ],
                                'weightDimensions' => [
                                    'length' => 0,
                                    'width' => 0,
                                    'height' => 0,
                                    'weight' => 0,
                                ],
                            ],
                            'mapping' => [
                                'marketSku' => 987654321,
                                'marketCategoryId' => 14247341,
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/tariffs/calculate' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offers' => [
                        [
                            'offer' => [
                                'categoryId' => 14247341,
                                'price' => 1999,
                                'weight' => 0.5,
                                'length' => 10,
                                'width' => 10,
                                'height' => 10,
                            ],
                            'tariffs' => [
                                [
                                    'type' => 'FEE',
                                    'amount' => 210,
                                    'currency' => 'RUR',
                                    'parameters' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        DB::table('yandex_market_products')->insert([
            'name' => 'Тестовый товар',
            'offer_id' => 'zero-dimensions',
            'sku' => 'SKU-0000',
            'category' => 'Тестовая категория',
            'status' => 'draft',
            'monitoring_enabled' => true,
            'campaign_ids' => json_encode([68023107], JSON_THROW_ON_ERROR),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/products');

        $response
            ->assertOk()
            ->assertJsonPath('products.0.market_service_cost', 210)
            ->assertJsonPath('products.0.market_service_cost_has_all_real_data', false)
            ->assertJsonPath('products.0.market_service_cost_missing_data.0', 'Длина')
            ->assertJsonPath('products.0.market_service_cost_missing_data.1', 'Ширина')
            ->assertJsonPath('products.0.market_service_cost_missing_data.2', 'Высота')
            ->assertJsonPath('products.0.market_service_cost_note', 'Отсутствуют некоторые данные');

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/tariffs/calculate')) {
                return false;
            }

            return data_get($request->data(), 'offers.0.length') === 10.0
                && data_get($request->data(), 'offers.0.width') === 10.0
                && data_get($request->data(), 'offers.0.height') === 10.0
                && data_get($request->data(), 'offers.0.weight') === 0.5;
        });
    }

    public function test_authenticated_user_can_search_products_in_yandex_market_catalog(): void
    {
        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.token', 'test-yandex-token');
        config()->set('integrations.yandex_market.business_id', null);

        Http::fake([
            'https://api.partner.market.yandex.ru/v2/campaigns' => Http::response([
                'status' => 'OK',
                'result' => [
                    'campaigns' => [
                        [
                            'business' => [
                                'id' => 987654,
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.partner.market.yandex.ru/v2/businesses/987654/offer-mappings*' => Http::response([
                'status' => 'OK',
                'result' => [
                    'offerMappings' => [
                        [
                            'offer' => [
                                'name' => 'Витамин D3 2000 IU',
                                'offerId' => 'vitamin-d3-2000',
                                'vendorCode' => 'SKU-1001',
                                'category' => 'Витамины',
                                'vendor' => 'Vitamin Lab',
                            ],
                            'mapping' => [
                                'marketSku' => 123456,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/yandex-market/catalog/search?query=витамин');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Витамин D3 2000 IU')
            ->assertJsonPath('products.0.offer_id', 'vitamin-d3-2000')
            ->assertJsonPath('products.0.sku', 'SKU-1001');
    }
}
