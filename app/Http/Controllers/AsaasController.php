<?php

namespace App\Http\Controllers;

use App\Services\AsaasGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class AsaasController extends Controller
{
    public function __construct(
        private readonly AsaasGateway $asaasGateway,
    ) {
    }

    public function checkout(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $checkout = $this->asaasGateway->checkoutFor($user);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('onboarding.show')
                ->with('onboarding_status', $exception->getMessage());
        }

        $user->subscription()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_code' => 'mensal-pro',
                'plan_name' => 'Plano Mensal Pro',
                'status' => $user->subscription?->status === 'trialing' ? 'trialing' : 'pending',
                'price_cents' => 3990,
                'currency' => 'BRL',
                'provider' => 'asaas',
                'provider_payment_link_id' => $checkout['payment_link_id'],
                'checkout_url' => $checkout['checkout_url'],
                'last_payment_status' => 'CHECKOUT_CREATED',
                'trial_ends_at' => $user->subscription?->trial_ends_at,
                'meta' => array_merge($user->subscription?->meta ?? [], [
                    'checkout_response' => $checkout['raw'],
                ]),
            ]
        );

        return redirect()->away($checkout['checkout_url']);
    }

    public function handleReturn(Request $request): RedirectResponse
    {
        $status = $request->string('status')->value() ?: 'pending';
        $message = $status === 'success'
            ? 'Pagamento enviado. Assim que a Asaas confirmar, sua assinatura sera liberada.'
            : 'Checkout encerrado. Voce pode tentar novamente quando quiser.';

        return redirect()->route('onboarding.show')->with('onboarding_status', $message);
    }

    public function pause(Request $request): RedirectResponse
    {
        $subscription = $request->user()->subscription;

        if (! $subscription) {
            return redirect()->route('profile.edit')->with('subscription_status', 'Nenhuma assinatura encontrada para pausar.');
        }

        try {
            $this->asaasGateway->pauseSubscription($subscription);
        } catch (\Throwable $exception) {
            return redirect()->route('profile.edit')->with('subscription_status', 'Nao foi possivel pausar a recorrencia no momento.');
        }

        return redirect()->route('profile.edit')->with('subscription_status', 'Assinatura pausada. Novas cobrancas recorrentes foram interrompidas.');
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $subscription = $request->user()->subscription;

        if (! $subscription) {
            return redirect()->route('profile.edit')->with('subscription_status', 'Nenhuma assinatura encontrada para reativar.');
        }

        try {
            if (filled($subscription->provider_subscription_id)) {
                $this->asaasGateway->reactivateSubscription($subscription);
            } else {
                return $this->checkout($request);
            }
        } catch (\Throwable $exception) {
            return redirect()->route('profile.edit')->with('subscription_status', 'Nao foi possivel reativar a assinatura agora.');
        }

        return redirect()->route('profile.edit')->with('subscription_status', 'Recorrencia reativada. O acesso volta quando a proxima cobranca for confirmada.');
    }

    public function webhook(Request $request): Response
    {
        $configuredToken = config('services.asaas.webhook_token');

        if (filled($configuredToken) && ! hash_equals((string) $configuredToken, (string) $request->header('asaas-access-token'))) {
            abort(403);
        }

        $this->asaasGateway->syncWebhook($request->all());

        return response()->noContent();
    }
}
