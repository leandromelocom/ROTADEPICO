<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_fetch_settings_with_bearer_token(): void
    {
        $plainToken = 'rtp_settings_token_1';

        $user = User::factory()->create([
            'vehicle_type' => 'Moto',
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson(route('api.mobile.settings.show'));

        $response->assertOk();
        $response->assertJsonPath('decision_settings.decision_profile', 'equilibrado');
        $response->assertJsonPath('cost_settings.fuel_consumption_km_per_l', 32);
    }

    public function test_settings_endpoint_requires_bearer_token(): void
    {
        $response = $this->getJson(route('api.mobile.settings.show'));

        $response->assertUnauthorized();
    }

    public function test_driver_can_update_decision_settings_from_mobile(): void
    {
        $plainToken = 'rtp_settings_token_2';

        $user = User::factory()->create([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->patchJson(route('api.mobile.settings.decision.update'), [
                'decision_profile' => 'premium',
                'min_offer_fare' => 60,
                'min_fare_per_km' => 4.0,
                'min_hourly_rate' => 70,
                'max_pickup_distance_km' => 2.0,
                'max_pickup_eta_minutes' => 5,
            ]);

        $response->assertOk();
        $response->assertJsonPath('decision_settings.decision_profile', 'premium');
        $response->assertJsonPath('decision_settings.min_hourly_rate', 70);

        $this->assertSame('premium', $user->fresh()->decision_profile);
    }

    public function test_decision_settings_update_validates_input(): void
    {
        $plainToken = 'rtp_settings_token_3';

        User::factory()->create([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->patchJson(route('api.mobile.settings.decision.update'), [
                'decision_profile' => 'invalido',
                'min_offer_fare' => 60,
                'min_fare_per_km' => 4.0,
                'min_hourly_rate' => 70,
                'max_pickup_distance_km' => 2.0,
                'max_pickup_eta_minutes' => 5,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('decision_profile');
    }

    public function test_driver_can_update_cost_settings_from_mobile(): void
    {
        $plainToken = 'rtp_settings_token_4';

        $user = User::factory()->create([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->patchJson(route('api.mobile.settings.cost.update'), [
                'fuel_consumption_km_per_l' => 11.5,
                'fuel_price_per_liter' => 5.89,
                'extra_cost_per_km' => 0.12,
            ]);

        $response->assertOk();
        $response->assertJsonPath('cost_settings.fuel_consumption_km_per_l', 11.5);

        $this->assertSame(11.5, (float) $user->fresh()->fuel_consumption_km_per_l);
    }
}
