<?php

namespace App\Support;

use App\Models\User;

class VehicleOperatingCost
{
    private const DEFAULT_FUEL_PRICE_PER_LITER = 6.10;

    private const VEHICLE_DEFAULTS = [
        'Moto' => ['fuel_consumption_km_per_l' => 32.0, 'extra_cost_per_km' => 0.04],
        'Carro' => ['fuel_consumption_km_per_l' => 12.0, 'extra_cost_per_km' => 0.10],
        'SUV' => ['fuel_consumption_km_per_l' => 9.0, 'extra_cost_per_km' => 0.15],
    ];

    public function forUser(User $user): array
    {
        $defaults = self::VEHICLE_DEFAULTS[$user->vehicle_type] ?? self::VEHICLE_DEFAULTS['Carro'];

        return [
            'fuel_consumption_km_per_l' => (float) ($user->fuel_consumption_km_per_l ?? $defaults['fuel_consumption_km_per_l']),
            'fuel_price_per_liter' => (float) ($user->fuel_price_per_liter ?? self::DEFAULT_FUEL_PRICE_PER_LITER),
            'extra_cost_per_km' => (float) ($user->extra_cost_per_km ?? $defaults['extra_cost_per_km']),
        ];
    }

    public function costPerKm(User $user): float
    {
        $profile = $this->forUser($user);

        $fuelCostPerKm = $profile['fuel_consumption_km_per_l'] > 0
            ? $profile['fuel_price_per_liter'] / $profile['fuel_consumption_km_per_l']
            : 0.0;

        return round($fuelCostPerKm + $profile['extra_cost_per_km'], 4);
    }
}
