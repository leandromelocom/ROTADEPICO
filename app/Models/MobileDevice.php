<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'device_id',
    'provider',
    'platform',
    'device_label',
    'package_name',
    'app_version',
    'last_notification_received_at',
    'last_decision_received_at',
    'last_seen_at',
])]
class MobileDevice extends Model
{
    protected function casts(): array
    {
        return [
            'last_notification_received_at' => 'datetime',
            'last_decision_received_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
