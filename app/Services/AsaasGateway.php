<?php

namespace App\Services;

use App\Models\Subscription;
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
        $apiKey = config('services.asaas.api_key');
        $apiUrl = rtrim((string) config('services.asaas.api_url'), '/');

        if (! filled($apiKey) || ! filled($apiUrl)) {
            throw new RuntimeException('Credenciais da Asaas nao configuradas.');
        }

        $response = Http::baseUrl($apiUrl)
            ->withHeader('access_token', $apiKey)
            ->acceptJson()
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
            'canceled_at' => in_array($status, ['canceled', 'expired'], true) ? now() : null,
            'meta' => [
                'event' => $payload['event'] ?? null,
                'payment_id' => $payment['id'] ?? null,
                'invoice_url' => $payment['invoiceUrl'] ?? null,
                'last_payload' => $payload,
            ],
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
}
