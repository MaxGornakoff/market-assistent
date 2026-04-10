<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_yandex_market_integration_settings(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        config()->set('integrations.yandex_market.token', null);
        config()->set('integrations.yandex_market.business_id', null);
        config()->set('integrations.yandex_market.campaign_id', null);

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/integrations/yandex-market')
            ->assertOk()
            ->assertJsonPath('settings.has_token', false);

        $response = $this
            ->actingAs($admin)
            ->putJson('/api/admin/integrations/yandex-market', [
                'api_url' => 'https://api.partner.market.yandex.ru',
                'business_id' => 77283184,
                'campaign_ids' => [68023107, 100655646],
                'token' => 'secret-token-123',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('settings.api_url', 'https://api.partner.market.yandex.ru')
            ->assertJsonPath('settings.business_id', 77283184)
            ->assertJsonPath('settings.campaign_ids.0', 68023107)
            ->assertJsonPath('settings.campaign_ids.1', 100655646)
            ->assertJsonPath('settings.has_token', true);

        $rawSettings = DB::table('integration_settings')
            ->where('key', 'yandex_market')
            ->value('settings');

        $this->assertNotNull($rawSettings);
        $this->assertStringNotContainsString('secret-token-123', $rawSettings);
    }

    public function test_admin_can_check_yandex_market_connection(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        config()->set('integrations.yandex_market.api_url', 'https://api.partner.market.yandex.ru');
        config()->set('integrations.yandex_market.business_id', 77283184);
        config()->set('integrations.yandex_market.token', 'test-yandex-token');

        Http::fake([
            'https://api.partner.market.yandex.ru/v2/campaigns' => Http::response([
                'campaigns' => [
                    [
                        'id' => 100655646,
                        'domain' => 'FBS',
                        'placementType' => 'FBS',
                        'business' => [
                            'id' => 77283184,
                            'name' => 'Витаминоф',
                        ],
                        'apiAvailability' => 'AVAILABLE',
                    ],
                ],
            ]),
        ]);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/integrations/yandex-market/check')
            ->assertOk()
            ->assertJsonPath('connection.connected', true)
            ->assertJsonPath('connection.business_id', 77283184)
            ->assertJsonPath('connection.business_name', 'Витаминоф')
            ->assertJsonCount(1, 'connection.campaigns');
    }

    public function test_manager_cannot_update_integration_settings(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this
            ->actingAs($manager)
            ->putJson('/api/admin/integrations/yandex-market', [
                'business_id' => 123456,
            ])
            ->assertForbidden();
    }
}
