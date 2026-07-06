<?php

namespace App\Http\Controllers;

use App\Support\RideOfferDecisionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideOfferDecisionController extends Controller
{
    public function __invoke(Request $request, RideOfferDecisionEngine $engine): JsonResponse
    {
        $user = $request->user();

        abort_if($user?->is_admin, 403);
        abort_unless($user?->hasCompletedOnboarding() && ($user->subscription?->isActive() ?? false), 403);

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:40'],
            'external_offer_id' => ['nullable', 'string', 'max:120'],
            'quoted_fare' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'pickup_distance_km' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trip_distance_km' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'pickup_eta_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'surge_multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'destination_zone_name' => ['nullable', 'string', 'max:140'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notification_text' => ['nullable', 'string', 'max:2000'],
            'raw_payload' => ['nullable', 'array'],
        ]);

        if (isset($validated['notification_text']) && ! isset($validated['raw_payload'])) {
            $validated['raw_payload'] = [
                'notification_text' => $validated['notification_text'],
            ];
        }

        return response()->json($engine->analyze($user, $validated));
    }
}
