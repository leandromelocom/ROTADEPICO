<?php

namespace App\Support;

use App\Models\User;

class DriverDecisionPreferences
{
    public function forUser(User $user): array
    {
        $profile = $user->decision_profile ?: 'equilibrado';

        $defaults = match ($profile) {
            'giro' => [
                'min_offer_fare' => 18.0,
                'min_fare_per_km' => 2.3,
                'min_hourly_rate' => 28.0,
                'max_pickup_distance_km' => 4.5,
                'max_pickup_eta_minutes' => 10,
            ],
            'premium' => [
                'min_offer_fare' => 32.0,
                'min_fare_per_km' => 3.4,
                'min_hourly_rate' => 45.0,
                'max_pickup_distance_km' => 2.5,
                'max_pickup_eta_minutes' => 6,
            ],
            default => [
                'min_offer_fare' => 24.0,
                'min_fare_per_km' => 2.8,
                'min_hourly_rate' => 36.0,
                'max_pickup_distance_km' => 3.2,
                'max_pickup_eta_minutes' => 8,
            ],
        };

        return [
            'decision_profile' => $profile,
            'min_offer_fare' => (float) ($user->min_offer_fare ?? $defaults['min_offer_fare']),
            'min_fare_per_km' => (float) ($user->min_fare_per_km ?? $defaults['min_fare_per_km']),
            'min_hourly_rate' => (float) ($user->min_hourly_rate ?? $defaults['min_hourly_rate']),
            'max_pickup_distance_km' => (float) ($user->max_pickup_distance_km ?? $defaults['max_pickup_distance_km']),
            'max_pickup_eta_minutes' => (int) ($user->max_pickup_eta_minutes ?? $defaults['max_pickup_eta_minutes']),
        ];
    }
}
