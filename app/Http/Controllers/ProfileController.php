<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\DecisionSettingsUpdateRequest;
use App\Support\DriverDecisionPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $tripSummary = $request->user()
            ->driverTrips()
            ->where('provider', 'uber')
            ->selectRaw('COUNT(*) as trips_count')
            ->selectRaw('COALESCE(SUM(fare), 0) as gross_fare')
            ->selectRaw('MAX(dropoff_at) as last_trip_at')
            ->first();

        return view('profile.edit', [
            'user' => $request->user(),
            'decisionSettings' => app(DriverDecisionPreferences::class)->forUser($request->user()),
            'uberConnection' => $request->user()->uberConnection,
            'tripSummary' => $tripSummary,
            'subscription' => $request->user()->subscription?->loadMissing('charges'),
            'mobileApiEndpoint' => route('api.mobile.offers.analyze'),
            'mobileListenerEndpoint' => route('api.mobile.listener.uber-offers.decision'),
            'mobileDevices' => $request->user()->mobileDevices()->take(5)->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateDecisionSettings(DecisionSettingsUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return Redirect::route('profile.edit')->with('decision_settings_status', 'preferencias-operacionais-atualizadas');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
