<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id',
    'provider',
    'provider_payment_id',
    'provider_subscription_id',
    'event',
    'status',
    'billing_type',
    'value_cents',
    'invoice_url',
    'due_date',
    'paid_at',
    'raw_payload',
])]
class SubscriptionCharge extends Model
{
    protected function casts(): array
    {
        return [
            'value_cents' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
