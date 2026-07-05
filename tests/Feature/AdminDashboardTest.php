<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\UberConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_platform_metrics(): void
    {
        $admin = User::factory()->admin()->create();

        $activeDriver = User::factory()->create([
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'onboarding_completed_at' => now(),
            'location_permission_granted_at' => now(),
        ]);

        $overdueDriver = User::factory()->create([
            'city' => 'Campinas',
            'vehicle_type' => 'Moto',
            'work_shift' => 'Tarde',
        ]);

        Subscription::query()->create([
            'user_id' => $activeDriver->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'active',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        Subscription::query()->create([
            'user_id' => $overdueDriver->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'overdue',
            'price_cents' => 3990,
            'currency' => 'BRL',
        ]);

        UberConnection::query()->create([
            'user_id' => $activeDriver->id,
            'uber_driver_uuid' => 'driver-123',
            'first_name' => 'Leandro',
            'last_name' => 'Melo',
            'email' => 'driver@example.com',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['partner.profile'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Operacao do Rotadepico');
        $response->assertSee('2');
        $response->assertSee('R$ 39,90');
        $response->assertSee($activeDriver->email);
        $response->assertSee($overdueDriver->email);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_admin_dashboard_route_redirects_from_driver_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
    }
}
