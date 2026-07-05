<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Support\OpportunityRadar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RadarLocationController extends Controller
{
    public function update(Request $request, OpportunityRadar $radar): JsonResponse
    {
        $user = $request->user();

        abort_if($user?->is_admin, 403);
        abort_unless($user?->hasCompletedOnboarding() && ($user->subscription?->isActive() ?? false), 403);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user->forceFill([
            'last_known_latitude' => $validated['latitude'],
            'last_known_longitude' => $validated['longitude'],
            'last_location_reported_at' => now(),
        ])->save();

        return response()->json(
            $radar->buildLocalizedForUser(
                $user,
                Opportunity::query()->get(),
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            )
        );
    }
}
