<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BrazilCityCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(BrazilCityCatalog $cityCatalog): View
    {
        return view('auth.register', [
            'brazilStates' => $cityCatalog->states(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, BrazilCityCatalog $cityCatalog): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'size:2'],
            'city' => [
                'required',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail) use ($cityCatalog): void {
                    if (! $cityCatalog->findOfficialCity((string) $value)) {
                        $fail('Selecione uma cidade valida da lista.');
                    }
                },
            ],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'work_shift' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'city' => $cityCatalog->findOfficialCity($request->city) ?? $request->city,
            'vehicle_type' => $request->vehicle_type,
            'work_shift' => $request->work_shift,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
