<?php

use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileOfferDecisionController;
use Illuminate\Support\Facades\Route;

Route::post('/mobile/auth/register', [MobileAuthController::class, 'register'])
    ->name('api.mobile.auth.register');
Route::post('/mobile/auth/login', [MobileAuthController::class, 'login'])
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
});
