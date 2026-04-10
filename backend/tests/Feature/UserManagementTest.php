<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manager_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Менеджер 1',
            'email' => 'manager1@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'manager',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'manager1@example.com')
            ->assertJsonPath('user.role', 'manager');

        $this->assertDatabaseHas('users', [
            'email' => 'manager1@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_manager_cannot_create_users(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Другой менеджер',
            'email' => 'manager2@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'manager',
        ]);

        $response->assertForbidden();
    }
}
