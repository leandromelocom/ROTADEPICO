<?php

use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileOfferDecisionController;
use App\Http\Controllers\MobileSettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/mobile/auth/register', [MobileAuthController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('api.mobile.auth.register');
Route::post('/mobile/auth/login', [MobileAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.mobile.auth.login');

Route::middleware('mobile.token')->group(function () {
    Route::get('/mobile/auth/me', [MobileAuthController::class, 'me'])
        ->name('api.mobile.auth.me');
    Route::post('/mobile/auth/logout', [MobileAuthController::class, 'logout'])
        ->name('api.mobile.auth.logout');

    Route::post('/mobile/offers/analyze', MobileOfferDecisionController::class)
        ->name('api.mobile.offers.analyze');

    Route::post('/mobile/listener/uber-offers/decision', MobileOfferDecisionController::class)
        ->name('api.mobile.listener.uber-offers.decision');

    Route::get('/mobile/settings', [MobileSettingsController::class, 'show'])
        ->name('api.mobile.settings.show');
    Route::patch('/mobile/settings/decision', [MobileSettingsController::class, 'updateDecisionSettings'])
        ->name('api.mobile.settings.decision.update');
    Route::patch('/mobile/settings/cost', [MobileSettingsController::class, 'updateCostSettings'])
        ->name('api.mobile.settings.cost.update');
});
