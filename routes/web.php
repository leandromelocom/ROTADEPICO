<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AsaasController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UberConnectionController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/profile', [OnboardingController::class, 'updateProfile'])->name('onboarding.profile');
    Route::post('/onboarding/location', [OnboardingController::class, 'activateLocation'])->name('onboarding.location');
    Route::post('/onboarding/subscription', [AsaasController::class, 'checkout'])->name('onboarding.subscription');
    Route::post('/onboarding/finish', [OnboardingController::class, 'finish'])->name('onboarding.finish');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/integrations/uber/redirect', [UberConnectionController::class, 'redirect'])->name('integrations.uber.redirect');
    Route::get('/integrations/uber/callback', [UberConnectionController::class, 'callback'])->name('integrations.uber.callback');
    Route::post('/integrations/uber/sync', [UberConnectionController::class, 'sync'])->name('integrations.uber.sync');
    Route::delete('/integrations/uber', [UberConnectionController::class, 'destroy'])->name('integrations.uber.destroy');
});

Route::get('/billing/asaas/return', [AsaasController::class, 'handleReturn'])->name('billing.asaas.return');
Route::post('/webhooks/asaas', [AsaasController::class, 'webhook'])
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('webhooks.asaas');

require __DIR__.'/auth.php';
