<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideOfferDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_score_a_notification_offer_as_worth_it(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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
            'zone_name' => 'Zona Premium Noturna',
            'score' => 93,
            'avg_fare' => 45.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:59',
            'active_driver_ratio' => 0.35,
            'pickup_hotspot' => 'Centro',
            'tip' => 'Centro aquecido',
            'trend' => 'subindo',
            'route_profile' => 'premium',
            'queue_pressure' => 18,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 48.90,
            'pickup_distance_km' => 1.2,
            'trip_distance_km' => 9.4,
            'pickup_eta_minutes' => 4,
            'surge_multiplier' => 1.4,
            'destination_zone_name' => 'Premium Noturna',
        ]);

        $response->assertOk();
        $response->assertJsonPath('recommendation', 'vale_a_pena');
        $response->assertJsonPath('matched_zone', 'Zona Premium Noturna');

        $this->assertDatabaseHas('ride_offer_evaluations', [
            'user_id' => $user->id,
            'recommendation' => 'vale_a_pena',
            'matched_opportunity_zone' => 'Zona Premium Noturna',
        ]);
    }

    public function test_driver_is_warned_when_destination_zone_is_bad(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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
            'zone_name' => 'Destino Fraco Leste',
            'score' => 41,
            'avg_fare' => 19.00,
            'surge_label' => 'Baixa',
            'demand_level' => 'Baixa',
            'best_start_at' => '10:00',
            'best_end_at' => '14:00',
            'active_driver_ratio' => 0.91,
            'pickup_hotspot' => 'Periferia',
            'tip' => 'Retorno fraco',
            'trend' => 'descendo',
            'route_profile' => 'retorno-fraco',
            'queue_pressure' => 84,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Tarde'],
        ]);

        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 23.50,
            'pickup_distance_km' => 4.6,
            'trip_distance_km' => 11.3,
            'pickup_eta_minutes' => 13,
            'destination_zone_name' => 'Fraco Leste',
        ]);

        $response->assertOk();
        $response->assertJsonPath('recommendation', 'regiao_destino_ruim');
        $response->assertJsonPath('destination_risk', 'high');
    }

    public function test_driver_can_analyze_offer_from_notification_text_only(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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
            'score' => 90,
            'avg_fare' => 40.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:59',
            'active_driver_ratio' => 0.33,
            'pickup_hotspot' => 'Zona Sul',
            'tip' => 'Boa area',
            'trend' => 'subindo',
            'route_profile' => 'premium',
            'queue_pressure' => 18,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'notification_text' => 'Uber: R$ 48,90, embarque a 4 min, a 1,2 km, destino Zona Sul Premium, 1,4x',
        ]);

        $response->assertOk();
        $response->assertJsonPath('offer.quoted_fare', 48.9);
        $response->assertJsonPath('offer.pickup_eta_minutes', 4);
        $response->assertJsonPath('matched_zone', 'Zona Sul Premium');
    }

    public function test_driver_preferences_can_reject_offer_below_personal_threshold(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
            'decision_profile' => 'premium',
            'min_offer_fare' => 60.00,
            'min_fare_per_km' => 4.00,
            'min_hourly_rate' => 70.00,
            'max_pickup_distance_km' => 2.00,
            'max_pickup_eta_minutes' => 5,
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
            'score' => 90,
            'avg_fare' => 40.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:59',
            'active_driver_ratio' => 0.33,
            'pickup_hotspot' => 'Zona Sul',
            'tip' => 'Boa area',
            'trend' => 'subindo',
            'route_profile' => 'premium',
            'queue_pressure' => 18,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 48.90,
            'pickup_distance_km' => 1.2,
            'trip_distance_km' => 9.4,
            'pickup_eta_minutes' => 4,
            'surge_multiplier' => 1.4,
            'destination_zone_name' => 'Zona Sul Premium',
        ]);

        $response->assertOk();
        $response->assertJsonPath('recommendation', 'nao_vale');
        $response->assertJsonPath('driver_preferences.decision_profile', 'premium');
    }

    public function test_high_gross_fare_but_high_operating_cost_flips_to_not_worth_it(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'SUV',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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

        // Tarifa bruta parece boa (R$ 50), mas a viagem eh longa (40km) num SUV: o custo
        // de combustivel come quase toda a tarifa. Bruto diria "vale a pena", liquido nao.
        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 50.00,
            'pickup_distance_km' => 2.0,
            'trip_distance_km' => 40.0,
            'pickup_eta_minutes' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('recommendation', 'nao_vale');

        $payload = $response->json();
        $this->assertNotNull($payload['net']['net_fare']);
        $this->assertLessThan($payload['offer']['quoted_fare'], $payload['net']['net_fare']);
        $this->assertLessThan(1.0, $payload['net']['net_fare_per_km']);
    }

    public function test_missing_trip_distance_still_estimates_operating_cost(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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

        $response = $this->actingAs($user)->postJson(route('radar.offer-decision'), [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 40.00,
            'pickup_distance_km' => 2.0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('offer.trip_distance_km', null);
        $response->assertJsonPath('net.cost_estimated', true);

        $payload = $response->json();
        $this->assertNotNull($payload['net']['net_fare']);
        $this->assertLessThan(40.0, $payload['net']['net_fare']);

        $this->assertDatabaseHas('ride_offer_evaluations', [
            'user_id' => $user->id,
            'cost_estimated' => true,
        ]);
    }

    public function test_surge_multiplier_does_not_inflate_score_independently(): void
    {
        $user = User::factory()->create([
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'onboarding_completed_at' => now(),
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

        $basePayload = [
            'provider' => 'uber',
            'source' => 'notification',
            'quoted_fare' => 35.00,
            'pickup_distance_km' => 2.0,
            'trip_distance_km' => 10.0,
            'pickup_eta_minutes' => 5,
        ];

        $withoutSurge = $this->actingAs($user)
            ->postJson(route('radar.offer-decision'), $basePayload)
            ->json();

        $withSurge = $this->actingAs($user)
            ->postJson(route('radar.offer-decision'), $basePayload + ['surge_multiplier' => 1.8])
            ->json();

        $this->assertSame($withoutSurge['decision_score'], $withSurge['decision_score']);
    }
}
