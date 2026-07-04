<?php

namespace App\Services;

use App\Models\DriverTrip;
use App\Models\UberConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

class UberDriverApi
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled(config('services.uber.client_id'))
            && filled(config('services.uber.client_secret'))
            && filled(config('services.uber.redirect'));
    }

    public function scopes(): array
    {
        return config('services.uber.scopes', ['partner.profile', 'partner.trips']);
    }

    public function redirectUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.uber.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.uber.redirect'),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
        ]);

        return rtrim(config('services.uber.auth_url'), '?').'?'.$query;
    }

    public function connect(User $user, string $code): UberConnection
    {
        $token = $this->exchangeCode($code);
        $profile = $this->profile($token['access_token']);

        return $user->uberConnection()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'uber_driver_uuid' => Arr::get($profile, 'driver_id'),
                'first_name' => Arr::get($profile, 'first_name'),
                'last_name' => Arr::get($profile, 'last_name'),
                'email' => Arr::get($profile, 'email'),
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0)),
                'scopes' => explode(' ', trim((string) ($token['scope'] ?? implode(' ', $this->scopes())))),
            ]
        );
    }

    public function syncTrips(UberConnection $connection, int $limit = 25): int
    {
        $accessToken = $this->validAccessToken($connection);

        $response = $this->http->acceptJson()
            ->withToken($accessToken)
            ->get(rtrim(config('services.uber.api_url'), '/').'/v1/partners/trips', [
                'limit' => min($limit, 50),
            ])
            ->throw()
            ->json();

        $synced = 0;

        foreach (Arr::get($response, 'trips', []) as $trip) {
            $connection->user->driverTrips()->updateOrCreate(
                [
                    'provider' => 'uber',
                    'external_trip_id' => $trip['trip_id'],
                ],
                [
                    'status' => Arr::get($trip, 'status'),
                    'accepted_at' => $this->extractStatusTimestamp($trip, 'accepted'),
                    'pickup_at' => $this->extractStatusTimestamp($trip, 'trip_began'),
                    'dropoff_at' => Arr::get($trip, 'dropoff.timestamp') ? CarbonImmutable::createFromTimestamp(Arr::get($trip, 'dropoff.timestamp')) : null,
                    'fare' => Arr::get($trip, 'fare'),
                    'currency_code' => Arr::get($trip, 'currency_code'),
                    'distance_miles' => Arr::get($trip, 'distance'),
                    'duration_seconds' => Arr::get($trip, 'duration'),
                    'surge_multiplier' => Arr::get($trip, 'surge_multiplier'),
                    'start_city_name' => Arr::get($trip, 'start_city.display_name'),
                    'start_city_latitude' => Arr::get($trip, 'start_city.latitude'),
                    'start_city_longitude' => Arr::get($trip, 'start_city.longitude'),
                    'raw_payload' => $trip,
                ]
            );

            $synced++;
        }

        $connection->forceFill(['last_synced_at' => now()])->save();

        return $synced;
    }

    public function disconnect(UberConnection $connection): void
    {
        $connection->delete();
    }

    private function exchangeCode(string $code): array
    {
        return $this->http->asForm()
            ->post(rtrim(config('services.uber.api_url'), '/').'/oauth/v2/token', [
                'client_id' => config('services.uber.client_id'),
                'client_secret' => config('services.uber.client_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.uber.redirect'),
                'code' => $code,
            ])
            ->throw()
            ->json();
    }

    private function refreshToken(UberConnection $connection): array
    {
        if (blank($connection->refresh_token)) {
            throw new RuntimeException('Uber refresh token not available.');
        }

        $token = $this->http->asForm()
            ->post(rtrim(config('services.uber.api_url'), '/').'/oauth/v2/token', [
                'client_id' => config('services.uber.client_id'),
                'client_secret' => config('services.uber.client_secret'),
                'grant_type' => 'refresh_token',
                'redirect_uri' => config('services.uber.redirect'),
                'refresh_token' => $connection->refresh_token,
            ])
            ->throw()
            ->json();

        $connection->forceFill([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0)),
            'scopes' => explode(' ', trim((string) ($token['scope'] ?? implode(' ', $this->scopes())))),
        ])->save();

        return $token;
    }

    private function profile(string $accessToken): array
    {
        return $this->http->acceptJson()
            ->withToken($accessToken)
            ->get(rtrim(config('services.uber.api_url'), '/').'/v1/partners/me')
            ->throw()
            ->json();
    }

    private function validAccessToken(UberConnection $connection): string
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        return $this->refreshToken($connection)['access_token'];
    }

    private function extractStatusTimestamp(array $trip, string $status): ?CarbonImmutable
    {
        $change = collect($trip['status_changes'] ?? [])
            ->firstWhere('status', $status);

        return isset($change['timestamp'])
            ? CarbonImmutable::createFromTimestamp((int) $change['timestamp'])
            : null;
    }
}
