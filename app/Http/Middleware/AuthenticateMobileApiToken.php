<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_unless($token, 401, 'Token mobile ausente.');

        $user = User::query()
            ->where('mobile_api_token_hash', hash('sha256', $token))
            ->first();

        abort_unless($user, 401, 'Token mobile invalido.');

        $user->forceFill([
            'mobile_api_token_last_used_at' => now(),
        ])->save();

        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}
