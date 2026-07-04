<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'city',
    'zone_name',
    'score',
    'avg_fare',
    'surge_label',
    'demand_level',
    'best_start_at',
    'best_end_at',
    'active_driver_ratio',
    'latitude',
    'longitude',
    'pickup_hotspot',
    'tip',
    'trend',
    'route_profile',
    'queue_pressure',
    'preferred_vehicle_types',
    'preferred_shifts',
])]
class Opportunity extends Model
{
    protected function casts(): array
    {
        return [
            'avg_fare' => 'decimal:2',
            'score' => 'integer',
            'active_driver_ratio' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'queue_pressure' => 'integer',
            'preferred_vehicle_types' => 'array',
            'preferred_shifts' => 'array',
        ];
    }
}
