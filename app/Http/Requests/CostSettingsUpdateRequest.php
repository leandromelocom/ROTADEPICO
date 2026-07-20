<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CostSettingsUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fuel_consumption_km_per_l' => ['required', 'numeric', 'min:1', 'max:100'],
            'fuel_price_per_liter' => ['required', 'numeric', 'min:1', 'max:20'],
            'extra_cost_per_km' => ['required', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
