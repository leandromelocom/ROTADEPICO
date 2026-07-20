<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_user_without_completed_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('onboarding.show'));
    }

    public function test_user_can_start_asaas_checkout_during_onboarding(): void
    {
        $user = User::factory()->create();

        Config::set('services.asaas.api_key', 'asaas-test-key');
        Config::set('services.asaas.api_url', 'https://api-sandbox.asaas.com');

        Http::fake([
            'https://api-sandbox.asaas.com/v3/paymentLinks' => Http::response([
                'id' => 'plink_123',
                'url' => 'https://sandbox.asaas.com/i/plink_123',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.subscription'));

        $response->assertRedirect('https://sandbox.asaas.com/i/plink_123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'provider' => 'asaas',
            'provider_payment_link_id' => 'plink_123',
            'status' => 'pending',
            'price_cents' => 3990,
        ]);
    }

    public function test_user_can_activate_seven_day_trial_during_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.trial'));

        $response->assertRedirect(route('onboarding.show'));

        $subscription = $user->fresh()->subscription;

        $this->assertNotNull($subscription);
        $this->assertSame('trialing', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
    }

    public function test_onboarding_page_makes_clear_that_trial_does_not_require_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('onboarding.show'));

        $response->assertOk();
        $response->assertSeeText('7 dias gratis sem cartao');
        $response->assertSeeText('Nao pedimos cartao nem PIX agora.');
    }

    public function test_checkout_does_not_cancel_active_trial(): void
    {
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'trialing',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'trial',
            'started_at' => now(),
            'trial_ends_at' => now()->addDays(7),
        ]);

        Config::set('services.asaas.api_key', 'asaas-test-key');
        Config::set('services.asaas.api_url', 'https://api-sandbox.asaas.com');

        Http::fake([
            'https://api-sandbox.asaas.com/v3/paymentLinks' => Http::response([
                'id' => 'plink_123',
                'url' => 'https://sandbox.asaas.com/i/plink_123',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.subscription'));

        $response->assertRedirect('https://sandbox.asaas.com/i/plink_123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'provider' => 'asaas',
            'provider_payment_link_id' => 'plink_123',
            'status' => 'trialing',
        ]);
    }

    public function test_user_can_finish_onboarding_once_requirements_are_met(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'location_permission_granted_at' => now(),
        ]);

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'active',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'started_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.finish'));

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_user_can_finish_onboarding_with_trialing_subscription(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'location_permission_granted_at' => now(),
        ]);

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'trialing',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'started_at' => now(),
            'trial_ends_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.finish'));

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }
}
