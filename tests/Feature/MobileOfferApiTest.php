<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileOfferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_generate_mobile_token_from_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.mobile-token'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('mobile_api_token');

        $this->assertNotNull($user->fresh()->mobile_api_token_hash);
    }

    public function test_mobile_api_accepts_bearer_token_and_parses_notification_text(): void
    {
        $plainToken = 'rtp_test_token_123';

        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
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

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Zona Sul Premium',
            'score' => 91,
            'avg_fare' => 43.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:59',
            'active_driver_ratio' => 0.34,
            'pickup_hotspot' => 'Morumbi',
            'tip' => 'Zona boa',
            'trend' => 'subindo',
            'route_profile' => 'premium',
            'queue_pressure' => 20,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson(route('api.mobile.offers.analyze'), [
                'provider' => 'uber',
                'source' => 'notification_listener',
                'notification_text' => 'Uber R$ 48,90 embarque a 4 min a 1,2 km destino Zona Sul Premium 1,4x',
            ]);

        $response->assertOk();
        $response->assertJsonPath('recommendation', 'vale_a_pena');
        $response->assertJsonPath('offer.quoted_fare', 48.9);
        $response->assertJsonPath('offer.pickup_eta_minutes', 4);
        $response->assertJsonPath('overlay.show', true);
        $response->assertJsonPath('overlay.tone', 'positive');
        $response->assertJsonPath('push_notification.title', 'Rota de Pico: Vale a pena');

        $this->assertNotNull($user->fresh()->mobile_api_token_last_used_at);
    }

    public function test_android_listener_endpoint_returns_overlay_payload(): void
    {
        $plainToken = 'rtp_listener_token_456';

        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
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

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Zona Sul Premium',
            'score' => 91,
            'avg_fare' => 43.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:59',
            'active_driver_ratio' => 0.34,
            'pickup_hotspot' => 'Morumbi',
            'tip' => 'Zona boa',
            'trend' => 'subindo',
            'route_profile' => 'premium',
            'queue_pressure' => 20,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson(route('api.mobile.listener.uber-offers.decision'), [
                'provider' => 'uber',
                'source' => 'notification_listener',
                'platform' => 'android',
                'package_name' => 'com.ubercab.driver',
                'notification_title' => 'Uber',
                'notification_text' => 'Uber: R$ 48,90, embarque a 4 min, a 1,2 km, destino Zona Sul Premium, 1,4x',
                'device_id' => 'pixel-7-leandro',
                'device_label' => 'Pixel 7 do Leandro',
                'app_version' => '0.1.0',
                'notification_received_at' => now()->toIso8601String(),
            ]);

        $response->assertOk();
        $response->assertJsonPath('listener_contract_version', 1);
        $response->assertJsonPath('listener.package_name', 'com.ubercab.driver');
        $response->assertJsonPath('listener.device_id', 'pixel-7-leandro');
        $response->assertJsonPath('device.registered', true);
        $response->assertJsonPath('device.id', 'pixel-7-leandro');
        $response->assertJsonPath('device.platform', 'android');
        $response->assertJsonPath('overlay.headline', 'Aceite rapido');
        $response->assertJsonPath('overlay.label', 'Vale a pena');
        $response->assertJson(fn ($json) => $json
            ->where('push_notification.title', 'Rota de Pico: Vale a pena')
            ->where('overlay.matched_zone', 'Zona Sul Premium')
            ->where('overlay.tone', 'positive')
            ->etc()
        );

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $user->id,
            'device_id' => 'pixel-7-leandro',
            'device_label' => 'Pixel 7 do Leandro',
            'platform' => 'android',
            'package_name' => 'com.ubercab.driver',
            'app_version' => '0.1.0',
        ]);
    }
}
