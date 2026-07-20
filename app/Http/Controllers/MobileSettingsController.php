<?php

namespace App\Http\Controllers;

use App\Http\Requests\CostSettingsUpdateRequest;
use App\Http\Requests\DecisionSettingsUpdateRequest;
use App\Support\DriverDecisionPreferences;
use App\Support\VehicleOperatingCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSettingsController extends Controller
{
    public function show(Request $request, DriverDecisionPreferences $preferences, VehicleOperatingCost $operatingCost): JsonResponse
    {
        return response()->json([
            'decision_settings' => $preferences->forUser($request->user()),
            'cost_settings' => $operatingCost->forUser($request->user()),
        ]);
    }

    public function updateDecisionSettings(DecisionSettingsUpdateRequest $request, DriverDecisionPreferences $preferences): JsonResponse
    {
        $request->user()->fill($request->validated())->save();

        return response()->json([
            'decision_settings' => $preferences->forUser($request->user()),
        ]);
    }

    public function updateCostSettings(CostSettingsUpdateRequest $request, VehicleOperatingCost $operatingCost): JsonResponse
    {
        $request->user()->fill($request->validated())->save();

        return response()->json([
            'cost_settings' => $operatingCost->forUser($request->user()),
        ]);
    }
}
