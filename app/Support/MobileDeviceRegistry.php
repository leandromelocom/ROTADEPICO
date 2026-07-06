<?php

namespace App\Support;

use App\Models\MobileDevice;
use App\Models\User;
use Carbon\CarbonImmutable;

class MobileDeviceRegistry
{
    public function register(User $user, array $payload): ?MobileDevice
    {
        $deviceId = trim((string) ($payload['device_id'] ?? ''));

        if ($deviceId === '') {
            return null;
        }

        $notificationAt = ! empty($payload['notification_received_at'])
            ? CarbonImmutable::parse((string) $payload['notification_received_at'])
            : null;

        $device = MobileDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'provider' => (string) ($payload['provider'] ?? 'uber'),
                'platform' => (string) ($payload['platform'] ?? 'android'),
                'device_label' => $payload['device_label'] ?? null,
                'package_name' => $payload['package_name'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'last_notification_received_at' => $notificationAt,
                'last_decision_received_at' => now(),
                'last_seen_at' => now(),
            ]
        );

        return $device;
    }
}
