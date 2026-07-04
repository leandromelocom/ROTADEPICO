<?php

namespace Tests\Feature;

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
}
