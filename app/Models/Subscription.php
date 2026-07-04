<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'plan_code',
    'plan_name',
    'status',
    'price_cents',
    'currency',
    'provider',
    'provider_customer_id',
    'provider_subscription_id',
    'provider_payment_link_id',
    'checkout_url',
    'last_payment_status',
    'meta',
    'started_at',
    'renews_at',
    'trial_ends_at',
    'canceled_at',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'price_cents' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && ($this->renews_at?->isFuture() || $this->trial_ends_at?->isFuture());
    }
}
