<?php

namespace App\Http\Controllers;

use App\Support\RideOfferDecisionEngine;
use App\Support\MobileDeviceRegistry;
use App\Support\MobileDecisionPayloadFactory;
use App\Support\UberNotificationParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileOfferDecisionController extends Controller
{
    public function __invoke(
        Request $request,
        RideOfferDecisionEngine $engine,
        UberNotificationParser $parser,
        MobileDecisionPayloadFactory $payloadFactory,
        MobileDeviceRegistry $deviceRegistry
    ): JsonResponse {
        $user = $request->user();

        abort_if($user->is_admin, 403, 'Conta administrativa nao usa esta API mobile.');
        abort_unless($user->hasCompletedOnboarding() && ($user->subscription?->isActive() ?? false), 403, 'Onboarding ou assinatura pendente.');

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:40'],
            'external_offer_id' => ['nullable', 'string', 'max:120'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'device_label' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'package_name' => ['nullable', 'string', 'max:160'],
            'notification_title' => ['nullable', 'string', 'max:160'],
            'notification_received_at' => ['nullable', 'date'],
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

        if (! empty($validated['notification_text'])) {
            $validated = array_merge($parser->parse($validated['notification_text']), $validated);
        }

        if (! isset($validated['raw_payload'])) {
            $validated['raw_payload'] = $request->all();
        }

        $analysis = $engine->analyze($user, $validated);
        $device = $deviceRegistry->register($user, $validated);

        return response()->json(array_merge(
            $analysis,
            $payloadFactory->build($analysis),
            [
                'listener' => [
                    'provider' => (string) ($validated['provider'] ?? 'uber'),
                    'source' => (string) ($validated['source'] ?? 'notification_listener'),
                    'package_name' => $validated['package_name'] ?? null,
                    'device_id' => $validated['device_id'] ?? null,
                    'device_label' => $validated['device_label'] ?? null,
                    'app_version' => $validated['app_version'] ?? null,
                    'received_at' => now()->toIso8601String(),
                ],
                'device' => [
                    'registered' => $device !== null,
                    'id' => $device?->device_id,
                    'platform' => $device?->platform,
                    'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
                ],
            ]
        ));
    }
}
