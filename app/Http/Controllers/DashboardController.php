<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Support\OpportunityRadar;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(OpportunityRadar $radar): View|RedirectResponse
    {
        if (auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        if (! auth()->user()->onboarding_completed_at || ! (auth()->user()->subscription?->isActive() ?? false)) {
            return redirect()->route('onboarding.show');
        }

        $opportunities = Opportunity::query()
            ->get();

        $radarData = $radar->buildForUser(auth()->user(), $opportunities);

        $radarData['recentOfferEvaluations'] = auth()->user()
            ->rideOfferEvaluations()
            ->take(3)
            ->get();

        return view('dashboard', $radarData);
    }
}
