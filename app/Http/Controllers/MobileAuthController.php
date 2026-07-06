<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MobileApiTokenIssuer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function register(Request $request, MobileApiTokenIssuer $issuer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'work_shift' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'city' => $validated['city'],
            'vehicle_type' => $validated['vehicle_type'],
            'work_shift' => $validated['work_shift'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        $plainToken = $issuer->issue($user);

        return response()->json($this->payload($user, $plainToken), 201);
    }

    public function login(Request $request, MobileApiTokenIssuer $issuer): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais invalidas.'],
            ]);
        }

        $plainToken = $issuer->issue($user);

        return response()->json($this->payload($user, $plainToken));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request, MobileApiTokenIssuer $issuer): JsonResponse
    {
        $issuer->revoke($request->user());

        return response()->json([
            'message' => 'Sessao mobile encerrada.',
        ]);
    }

    private function payload(User $user, string $plainToken): array
    {
        return [
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'city' => $user->city,
            'vehicle_type' => $user->vehicle_type,
            'work_shift' => $user->work_shift,
            'subscription_active' => $user->subscription?->isActive() ?? false,
            'onboarding_completed' => $user->hasCompletedOnboarding(),
        ];
    }
}
