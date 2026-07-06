<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'source',
    'external_offer_id',
    'quoted_fare',
    'currency_code',
    'pickup_distance_km',
    'trip_distance_km',
    'pickup_eta_minutes',
    'surge_multiplier',
    'destination_zone_name',
    'destination_latitude',
    'destination_longitude',
    'decision_score',
    'recommendation',
    'risk_level',
    'destination_risk',
    'matched_opportunity_zone',
    'projected_hourly_rate',
    'reasons',
    'raw_payload',
    'evaluated_at',
])]
class RideOfferEvaluation extends Model
{
    protected function casts(): array
    {
        return [
            'quoted_fare' => 'decimal:2',
            'pickup_distance_km' => 'decimal:2',
            'trip_distance_km' => 'decimal:2',
            'pickup_eta_minutes' => 'integer',
            'surge_multiplier' => 'decimal:2',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
            'decision_score' => 'integer',
            'projected_hourly_rate' => 'decimal:2',
            'reasons' => 'array',
            'raw_payload' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
