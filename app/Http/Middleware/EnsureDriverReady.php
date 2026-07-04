<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $subscriptionActive = $user->subscription?->isActive() ?? false;

        if (! $user->onboarding_completed_at || ! $subscriptionActive) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
