<?php

namespace Tests\Feature;

use App\Models\SubscriptionCharge;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_activates_local_subscription_when_payment_is_confirmed(): void
    {
        Config::set('services.asaas.webhook_token', 'webhook-secret');

        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'pending',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'provider_payment_link_id' => 'plink_123',
        ]);

        $response = $this->withHeader('asaas-access-token', 'webhook-secret')
            ->postJson(route('webhooks.asaas'), [
                'event' => 'PAYMENT_CONFIRMED',
                'payment' => [
                    'id' => 'pay_123',
                    'customer' => 'cus_123',
                    'subscription' => 'sub_123',
                    'paymentLink' => 'plink_123',
                    'status' => 'CONFIRMED',
                    'dueDate' => now()->toDateString(),
                    'confirmedDate' => now()->toDateString(),
                    'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_123',
                ],
            ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
            'provider_customer_id' => 'cus_123',
            'provider_subscription_id' => 'sub_123',
            'last_payment_status' => 'CONFIRMED',
        ]);

        $this->assertDatabaseHas('subscription_charges', [
            'subscription_id' => $user->subscription->id,
            'provider_payment_id' => 'pay_123',
            'status' => 'CONFIRMED',
        ]);
    }

    public function test_webhook_marks_subscription_as_overdue_and_keeps_charge_history(): void
    {
        Config::set('services.asaas.webhook_token', 'webhook-secret');

        $user = User::factory()->create();

        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'active',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'provider_payment_link_id' => 'plink_123',
            'provider_subscription_id' => 'sub_123',
            'renews_at' => now()->addMonth(),
        ]);

        $response = $this->withHeader('asaas-access-token', 'webhook-secret')
            ->postJson(route('webhooks.asaas'), [
                'event' => 'PAYMENT_OVERDUE',
                'payment' => [
                    'id' => 'pay_999',
                    'customer' => 'cus_123',
                    'subscription' => 'sub_123',
                    'paymentLink' => 'plink_123',
                    'status' => 'OVERDUE',
                    'billingType' => 'CREDIT_CARD',
                    'value' => 39.90,
                    'dueDate' => now()->toDateString(),
                    'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_999',
                ],
            ]);

        $response->assertNoContent();

        $subscription->refresh();

        $this->assertSame('overdue', $subscription->status);
        $this->assertFalse($subscription->isActive());

        $this->assertDatabaseHas('subscription_charges', [
            'subscription_id' => $subscription->id,
            'provider_payment_id' => 'pay_999',
            'status' => 'OVERDUE',
        ]);
    }

    public function test_webhook_requires_configured_token_when_present(): void
    {
        Config::set('services.asaas.webhook_token', 'webhook-secret');

        $response = $this->postJson(route('webhooks.asaas'), [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'paymentLink' => 'plink_123',
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_subscription_events_can_inactivate_a_subscription(): void
    {
        Config::set('services.asaas.webhook_token', 'webhook-secret');

        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'mensal-pro',
            'plan_name' => 'Plano Mensal Pro',
            'status' => 'active',
            'price_cents' => 3990,
            'currency' => 'BRL',
            'provider' => 'asaas',
            'provider_subscription_id' => 'sub_123',
        ]);

        $response = $this->withHeader('asaas-access-token', 'webhook-secret')
            ->postJson(route('webhooks.asaas'), [
                'event' => 'SUBSCRIPTION_INACTIVATED',
                'subscription' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'status' => 'INACTIVE',
                    'nextDueDate' => now()->addMonth()->toDateString(),
                ],
            ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'inactive',
            'provider_customer_id' => 'cus_123',
        ]);
    }
}
