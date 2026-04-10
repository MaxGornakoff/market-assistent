<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
