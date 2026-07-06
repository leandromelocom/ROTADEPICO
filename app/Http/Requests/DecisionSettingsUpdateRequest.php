<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecisionSettingsUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision_profile' => ['required', 'string', 'in:giro,equilibrado,premium'],
            'min_offer_fare' => ['required', 'numeric', 'min:5', 'max:500'],
            'min_fare_per_km' => ['required', 'numeric', 'min:0.5', 'max:50'],
            'min_hourly_rate' => ['required', 'numeric', 'min:10', 'max:500'],
            'max_pickup_distance_km' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'max_pickup_eta_minutes' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
