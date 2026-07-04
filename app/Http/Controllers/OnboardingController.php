<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $subscription = $user->subscription;
        $uberConnection = $user->uberConnection;

        return view('onboarding.show', [
            'user' => $user,
            'subscription' => $subscription,
            'uberConnection' => $uberConnection,
            'checklist' => [
                'profile' => filled($user->phone) && filled($user->city) && filled($user->vehicle_type) && filled($user->work_shift),
                'location' => (bool) $user->location_permission_granted_at,
                'subscription' => $subscription?->isActive() ?? false,
                'uber' => (bool) $uberConnection,
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'work_shift' => ['required', 'string', 'max:50'],
        ]);

        $request->user()->fill($validated)->save();

        return redirect()->route('onboarding.show')->with('onboarding_status', 'Perfil operacional atualizado.');
    }

    public function activateLocation(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'location_permission_granted_at' => now(),
        ])->save();

        return redirect()->route('onboarding.show')->with('onboarding_status', 'Geolocalizacao habilitada no onboarding.');
    }

    public function finish(Request $request): RedirectResponse
    {
        $user = $request->user()->loadMissing('subscription', 'uberConnection');

        $profileReady = filled($user->phone) && filled($user->city) && filled($user->vehicle_type) && filled($user->work_shift);
        $subscriptionReady = $user->subscription?->isActive() ?? false;
        $locationReady = (bool) $user->location_permission_granted_at;

        if (! ($profileReady && $subscriptionReady && $locationReady)) {
            return redirect()->route('onboarding.show')
                ->with('onboarding_status', 'Conclua perfil, localizacao e assinatura antes de entrar no radar.');
        }

        $user->forceFill([
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect()->route('dashboard');
    }
}
