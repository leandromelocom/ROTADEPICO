<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_prioritizes_opportunities_that_fit_the_driver_profile(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'vehicle_type' => 'Moto',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'location_permission_granted_at' => now(),
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
            'zone_name' => 'Zona Premium',
            'score' => 95,
            'avg_fare' => 44.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '07:00',
            'best_end_at' => '09:00',
            'active_driver_ratio' => 0.85,
            'pickup_hotspot' => 'Empresarial',
            'tip' => 'Zona de teste premium',
            'trend' => 'estavel',
            'route_profile' => 'premium',
            'queue_pressure' => 82,
            'preferred_vehicle_types' => ['Carro', 'SUV'],
            'preferred_shifts' => ['Manha'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Zona Giro Moto',
            'score' => 84,
            'avg_fare' => 28.00,
            'surge_label' => 'Rotacao',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:00',
            'active_driver_ratio' => 0.42,
            'pickup_hotspot' => 'Bares',
            'tip' => 'Zona de giro',
            'trend' => 'subindo',
            'route_profile' => 'giro',
            'queue_pressure' => 25,
            'preferred_vehicle_types' => ['Moto'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeInOrder(['Zona Giro Moto', 'Zona Premium']);
    }

    public function test_localized_radar_returns_distance_aware_recommendations(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'location_permission_granted_at' => now(),
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
            'zone_name' => 'Zona Local Forte',
            'score' => 87,
            'avg_fare' => 37.00,
            'surge_label' => 'Alta',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:00',
            'active_driver_ratio' => 0.40,
            'latitude' => -23.5507,
            'longitude' => -46.6338,
            'pickup_hotspot' => 'Centro empresarial',
            'tip' => 'Perto do motorista',
            'trend' => 'subindo',
            'route_profile' => 'giro-noturno',
            'queue_pressure' => 24,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Zona Distante Premium',
            'score' => 95,
            'avg_fare' => 52.00,
            'surge_label' => 'Premium',
            'demand_level' => 'Alta',
            'best_start_at' => '18:00',
            'best_end_at' => '23:00',
            'active_driver_ratio' => 0.60,
            'latitude' => -23.6200,
            'longitude' => -46.7000,
            'pickup_hotspot' => 'Aeroporto',
            'tip' => 'Melhor ticket, mas bem mais longe',
            'trend' => 'subindo',
            'route_profile' => 'ticket-longo',
            'queue_pressure' => 40,
            'preferred_vehicle_types' => ['Carro'],
            'preferred_shifts' => ['Noite'],
        ]);

        $response = $this->actingAs($user)->postJson(route('radar.location'), [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertOk();
        $response->assertJsonPath('bestNow.zone_name', 'Zona Local Forte');
        $response->assertJsonStructure([
            'bestNow',
            'nearbyComparisons',
            'hourlyRankings',
            'shiftForecasts',
            'mapZones',
        ]);

        $this->assertNotNull($user->fresh()->last_location_reported_at);
    }

    public function test_dashboard_still_renders_when_there_are_no_opportunities(): void
    {
        $user = User::factory()->create([
            'phone' => '(11) 99999-0000',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'city' => 'Sao Paulo',
            'location_permission_granted_at' => now(),
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

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Sem zonas cadastradas');
    }
}
