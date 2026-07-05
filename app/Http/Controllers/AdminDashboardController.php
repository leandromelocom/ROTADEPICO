<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Support\ProductionReadiness;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, ProductionReadiness $readiness): View
    {
        abort_unless($request->user()?->is_admin, 403);

        $drivers = User::query()
            ->where('is_admin', false)
            ->with(['subscription', 'uberConnection'])
            ->latest()
            ->get();

        $activeStatuses = ['active', 'trialing'];
        $overdueStatuses = ['overdue'];

        $activeSubscriptions = Subscription::query()
            ->whereIn('status', $activeStatuses);

        return view('admin.dashboard', [
            'stats' => [
                'drivers_total' => $drivers->count(),
                'active_subscriptions' => Subscription::query()->whereIn('status', $activeStatuses)->count(),
                'overdue_subscriptions' => Subscription::query()->whereIn('status', $overdueStatuses)->count(),
                'uber_connected' => $drivers->filter(fn (User $user): bool => $user->uberConnection !== null)->count(),
                'onboarding_completed' => $drivers->filter(fn (User $user): bool => $user->onboarding_completed_at !== null)->count(),
                'monthly_revenue_cents' => (int) $activeSubscriptions->sum('price_cents'),
            ],
            'drivers' => $drivers,
            'readiness' => $readiness->report(),
        ]);
    }
}
