<?php

namespace Tests\Feature;

use App\Models\UberConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UberConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_uber_is_blocked_when_credentials_are_missing(): void
    {
        $user = User::factory()->create();

        Config::set('services.uber.client_id', null);
        Config::set('services.uber.client_secret', null);
        Config::set('services.uber.redirect', null);

        $response = $this->actingAs($user)->get(route('integrations.uber.redirect'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('uber_status');
    }

    public function test_callback_connects_account_and_syncs_trips(): void
    {
        $user = User::factory()->create();

        Config::set('services.uber.client_id', 'client-id');
        Config::set('services.uber.client_secret', 'client-secret');
        Config::set('services.uber.redirect', 'http://localhost/integrations/uber/callback');
        Config::set('services.uber.api_url', 'https://api.uber.com');

        Http::fake([
            'https://api.uber.com/oauth/v2/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'scope' => 'partner.profile partner.trips',
            ]),
            'https://api.uber.com/v1/partners/me' => Http::response([
                'driver_id' => 'driver-123',
                'first_name' => 'Uber',
                'last_name' => 'Driver',
                'email' => 'uber-driver@example.com',
            ]),
            'https://api.uber.com/v1/partners/trips*' => Http::response([
                'count' => 1,
                'limit' => 25,
                'offset' => 0,
                'trips' => [[
                    'trip_id' => 'trip-123',
                    'status' => 'completed',
                    'fare' => 42.5,
                    'currency_code' => 'BRL',
                    'distance' => 7.5,
                    'duration' => 930,
                    'surge_multiplier' => 1.6,
                    'start_city' => [
                        'display_name' => 'Sao Paulo',
                        'latitude' => -23.55,
                        'longitude' => -46.63,
                    ],
                    'dropoff' => ['timestamp' => now()->timestamp],
                    'status_changes' => [
                        ['status' => 'accepted', 'timestamp' => now()->subMinutes(25)->timestamp],
                        ['status' => 'trip_began', 'timestamp' => now()->subMinutes(20)->timestamp],
                        ['status' => 'completed', 'timestamp' => now()->timestamp],
                    ],
                ]],
            ]),
        ]);

        $state = 'oauth-state';

        $response = $this->actingAs($user)
            ->withSession(['uber_oauth_state' => $state])
            ->get(route('integrations.uber.callback', [
                'state' => $state,
                'code' => 'authorization-code',
            ]));

        $response->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('uber_connections', [
            'user_id' => $user->id,
            'uber_driver_uuid' => 'driver-123',
            'email' => 'uber-driver@example.com',
        ]);

        $this->assertDatabaseHas('driver_trips', [
            'user_id' => $user->id,
            'provider' => 'uber',
            'external_trip_id' => 'trip-123',
            'status' => 'completed',
        ]);
    }

    public function test_sync_endpoint_requires_existing_connection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('integrations.uber.sync'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('uber_status', 'Nenhuma conta Uber vinculada.');
    }
}
