<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_pause_subscription_recurring_cycle(): void
    {
        Config::set('services.asaas.api_key', 'asaas-test-key');
        Config::set('services.asaas.api_url', 'https://api-sandbox.asaas.com');

        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'active',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'provider_subscription_id' => 'sub_123',
            'renews_at' => now()->addMonth(),
        ]);

        Http::fake([
            'https://api-sandbox.asaas.com/v3/subscriptions/sub_123' => Http::response([
                'id' => 'sub_123',
                'status' => 'INACTIVE',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('subscription.pause'));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'inactive',
        ]);
    }

    public function test_driver_can_reactivate_subscription_recurring_cycle(): void
    {
        Config::set('services.asaas.api_key', 'asaas-test-key');
        Config::set('services.asaas.api_url', 'https://api-sandbox.asaas.com');

        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'inactive',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'provider_subscription_id' => 'sub_123',
        ]);

        Http::fake([
            'https://api-sandbox.asaas.com/v3/subscriptions/sub_123' => Http::response([
                'id' => 'sub_123',
                'status' => 'ACTIVE',
                'nextDueDate' => now()->addDay()->toDateString(),
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('subscription.reactivate'));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_overdue_subscription_is_blocked_from_dashboard(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'location_permission_granted_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'overdue',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'renews_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('onboarding.show'));
    }
}
