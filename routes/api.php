<?php

use App\Http\Controllers\MobileOfferDecisionController;
use Illuminate\Support\Facades\Route;

Route::middleware('mobile.token')->group(function () {
    Route::post('/mobile/offers/analyze', MobileOfferDecisionController::class)
        ->name('api.mobile.offers.analyze');
});
