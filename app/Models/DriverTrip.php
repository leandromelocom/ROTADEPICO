<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'external_trip_id',
    'status',
    'accepted_at',
    'pickup_at',
    'dropoff_at',
    'fare',
    'currency_code',
    'distance_miles',
    'duration_seconds',
    'surge_multiplier',
    'start_city_name',
    'start_city_latitude',
    'start_city_longitude',
    'raw_payload',
])]
class DriverTrip extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'pickup_at' => 'datetime',
            'dropoff_at' => 'datetime',
            'fare' => 'decimal:2',
            'distance_miles' => 'decimal:2',
            'duration_seconds' => 'integer',
            'surge_multiplier' => 'decimal:2',
            'start_city_latitude' => 'decimal:6',
            'start_city_longitude' => 'decimal:6',
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
