<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionCharge;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AsaasGateway
{
    public function checkoutFor(User $user): array
    {
        $response = $this->client()
            ->post('/v3/paymentLinks', [
                'name' => 'Radar Pro '.$user->id,
                'description' => 'Assinatura mensal do radar para motoristas',
                'billingType' => 'CREDIT_CARD',
                'chargeType' => 'RECURRENT',
                'subscriptionCycle' => 'MONTHLY',
                'value' => 39.90,
                'notificationEnabled' => true,
                'callback' => [
                    'successUrl' => route('billing.asaas.return', ['status' => 'success']),
                    'cancelUrl' => route('billing.asaas.return', ['status' => 'cancel']),
                    'autoRedirect' => false,
                ],
            ]);

        try {
            $payload = $response->throw()->json();
        } catch (RequestException $exception) {
            Log::warning('Falha ao criar link de pagamento Asaas.', [
                'user_id' => $user->id,
                'response' => $response->json(),
            ]);

            throw $exception;
        }

        if (! filled($payload['url'] ?? null)) {
            throw new RuntimeException('A Asaas nao retornou a URL do checkout.');
        }

        return [
            'payment_link_id' => $payload['id'] ?? null,
            'checkout_url' => $payload['url'] ?? null,
            'raw' => $payload,
        ];
    }

    public function syncWebhook(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');

        if (str_starts_with($event, 'SUBSCRIPTION_')) {
            $this->syncSubscriptionWebhook($payload);
            return;
        }

        $this->syncPaymentWebhook($payload);
    }

    public function pauseSubscription(Subscription $subscription): void
    {
        $providerSubscriptionId = $subscription->provider_subscription_id;

        if (! filled($providerSubscriptionId)) {
            throw new RuntimeException('A assinatura ainda nao possui recorrencia remota ativa para pausar.');
        }

        $response = $this->client()->put("/v3/subscriptions/{$providerSubscriptionId}", [
            'status' => 'INACTIVE',
        ]);

        $response->throw();

        $subscription->forceFill([
            'status' => 'inactive',
            'canceled_at' => now(),
            'meta' => array_merge($subscription->meta ?? [], [
                'pause_response' => $response->json(),
            ]),
        ])->save();
    }

    public function reactivateSubscription(Subscription $subscription): void
    {
        $providerSubscriptionId = $subscription->provider_subscription_id;

        if (! filled($providerSubscriptionId)) {
            throw new RuntimeException('A assinatura ainda nao possui recorrencia remota para reativar.');
        }

        $nextDueDate = $subscription->renews_at?->isFuture()
            ? $subscription->renews_at->toDateString()
            : now()->addDay()->toDateString();

        $response = $this->client()->put("/v3/subscriptions/{$providerSubscriptionId}", [
            'status' => 'ACTIVE',
            'nextDueDate' => $nextDueDate,
        ]);

        $response->throw();

        $subscription->forceFill([
            'status' => 'pending',
            'canceled_at' => null,
            'renews_at' => Carbon::parse($nextDueDate),
            'meta' => array_merge($subscription->meta ?? [], [
                'reactivation_response' => $response->json(),
            ]),
        ])->save();
    }

    private function syncPaymentWebhook(array $payload): void
    {
        $payment = $payload['payment'] ?? [];
        $subscription = $this->findSubscription($payment);

        if (! $subscription) {
            Log::info('Webhook Asaas ignorado por falta de assinatura local.', [
                'event' => $payload['event'] ?? null,
                'payment_link' => $payment['paymentLink'] ?? null,
                'subscription_id' => $payment['subscription'] ?? null,
            ]);

            return;
        }

        $status = $this->resolveLocalStatus(
            (string) ($payload['event'] ?? ''),
            (string) ($payment['status'] ?? '')
        );

        $renewsAt = $this->resolveRenewDate($payment, $status);
        $paidAt = $payment['clientPaymentDate'] ?? $payment['confirmedDate'] ?? null;

        $subscription->fill([
            'status' => $status,
            'provider' => 'asaas',
            'provider_customer_id' => $payment['customer'] ?? $subscription->provider_customer_id,
            'provider_subscription_id' => $payment['subscription'] ?? $subscription->provider_subscription_id,
            'provider_payment_link_id' => $payment['paymentLink'] ?? $subscription->provider_payment_link_id,
            'last_payment_status' => $payment['status'] ?? $payload['event'] ?? null,
            'started_at' => $subscription->started_at ?? ($paidAt ? Carbon::parse($paidAt) : null),
            'renews_at' => $renewsAt,
            'canceled_at' => in_array($status, ['canceled', 'inactive'], true) ? now() : null,
            'meta' => array_merge($subscription->meta ?? [], [
                'event' => $payload['event'] ?? null,
                'payment_id' => $payment['id'] ?? null,
                'invoice_url' => $payment['invoiceUrl'] ?? null,
                'last_payload' => $payload,
            ]),
        ])->save();

        $this->storeChargeHistory($subscription, $payload, $payment);
    }

    private function syncSubscriptionWebhook(array $payload): void
    {
        $remoteSubscription = $payload['subscription'] ?? [];
        $subscription = Subscription::query()
            ->where('provider_subscription_id', $remoteSubscription['id'] ?? null)
            ->orWhere('provider_payment_link_id', $remoteSubscription['paymentLink'] ?? null)
            ->first();

        if (! $subscription) {
            return;
        }

        $status = match ((string) ($payload['event'] ?? '')) {
            'SUBSCRIPTION_CREATED', 'SUBSCRIPTION_UPDATED' => strtolower((string) ($remoteSubscription['status'] ?? 'pending')) === 'active'
                ? 'active'
                : 'inactive',
            'SUBSCRIPTION_INACTIVATED' => 'inactive',
            'SUBSCRIPTION_DELETED' => 'canceled',
            default => $subscription->status,
        };

        $subscription->forceFill([
            'provider' => 'asaas',
            'provider_customer_id' => $remoteSubscription['customer'] ?? $subscription->provider_customer_id,
            'provider_subscription_id' => $remoteSubscription['id'] ?? $subscription->provider_subscription_id,
            'provider_payment_link_id' => $remoteSubscription['paymentLink'] ?? $subscription->provider_payment_link_id,
            'status' => $status,
            'renews_at' => filled($remoteSubscription['nextDueDate'] ?? null)
                ? Carbon::parse($remoteSubscription['nextDueDate'])
                : $subscription->renews_at,
            'canceled_at' => in_array($status, ['inactive', 'canceled'], true) ? now() : null,
            'meta' => array_merge($subscription->meta ?? [], [
                'subscription_event' => $payload['event'] ?? null,
                'subscription_payload' => $payload,
            ]),
        ])->save();
    }

    private function findSubscription(array $payment): ?Subscription
    {
        $providerSubscriptionId = $payment['subscription'] ?? null;
        $paymentLinkId = $payment['paymentLink'] ?? null;

        return Subscription::query()
            ->where(function ($query) use ($providerSubscriptionId, $paymentLinkId): void {
                if ($providerSubscriptionId) {
                    $query->where('provider_subscription_id', $providerSubscriptionId);
                }

                if ($paymentLinkId) {
                    $method = $providerSubscriptionId ? 'orWhere' : 'where';
                    $query->{$method}('provider_payment_link_id', $paymentLinkId);
                }
            })
            ->first();
    }

    private function resolveLocalStatus(string $event, string $paymentStatus): string
    {
        return match (true) {
            in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true),
            in_array($paymentStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true) => 'active',
            in_array($event, ['PAYMENT_OVERDUE', 'PAYMENT_DUNNING_REQUESTED'], true),
            $paymentStatus === 'OVERDUE' => 'overdue',
            in_array($event, ['PAYMENT_DELETED', 'PAYMENT_REFUNDED'], true),
            in_array($paymentStatus, ['REFUNDED', 'DELETED'], true) => 'canceled',
            in_array($event, ['PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE'], true) => 'chargeback',
            in_array($event, ['PAYMENT_CREATED', 'PAYMENT_UPDATED'], true),
            in_array($paymentStatus, ['PENDING', 'AWAITING_PAYMENT'], true) => 'pending',
            default => 'pending',
        };
    }

    private function resolveRenewDate(array $payment, string $status): ?Carbon
    {
        $dueDate = $payment['dueDate'] ?? null;

        if (! $dueDate) {
            return null;
        }

        $baseDate = Carbon::parse($dueDate);

        return $status === 'active'
            ? $baseDate->copy()->addMonth()
            : $baseDate;
    }

    private function storeChargeHistory(Subscription $subscription, array $payload, array $payment): void
    {
        if (! filled($payment['id'] ?? null)) {
            return;
        }

        SubscriptionCharge::query()->updateOrCreate(
            ['provider_payment_id' => $payment['id']],
            [
                'subscription_id' => $subscription->id,
                'provider' => 'asaas',
                'provider_subscription_id' => $payment['subscription'] ?? $subscription->provider_subscription_id,
                'event' => $payload['event'] ?? null,
                'status' => $payment['status'] ?? null,
                'billing_type' => $payment['billingType'] ?? null,
                'value_cents' => isset($payment['value']) ? (int) round(((float) $payment['value']) * 100) : null,
                'invoice_url' => $payment['invoiceUrl'] ?? null,
                'due_date' => filled($payment['dueDate'] ?? null) ? Carbon::parse($payment['dueDate'])->toDateString() : null,
                'paid_at' => filled($payment['clientPaymentDate'] ?? null)
                    ? Carbon::parse($payment['clientPaymentDate'])
                    : (filled($payment['confirmedDate'] ?? null) ? Carbon::parse($payment['confirmedDate']) : null),
                'raw_payload' => $payload,
            ]
        );
    }

    private function client()
    {
        $apiKey = config('services.asaas.api_key');
        $apiUrl = rtrim((string) config('services.asaas.api_url'), '/');

        if (! filled($apiKey) || ! filled($apiUrl)) {
            throw new RuntimeException('Credenciais da Asaas nao configuradas.');
        }

        return Http::baseUrl($apiUrl)
            ->withHeader('access_token', $apiKey)
            ->acceptJson();
    }
}
