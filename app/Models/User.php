<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
    'phone',
    'city',
    'vehicle_type',
    'work_shift',
    'last_known_latitude',
    'last_known_longitude',
    'last_location_reported_at',
    'mobile_api_token_hash',
    'mobile_api_token_created_at',
    'mobile_api_token_last_used_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'onboarding_completed_at' => 'datetime',
            'location_permission_granted_at' => 'datetime',
            'last_known_latitude' => 'float',
            'last_known_longitude' => 'float',
            'last_location_reported_at' => 'datetime',
            'mobile_api_token_created_at' => 'datetime',
            'mobile_api_token_last_used_at' => 'datetime',
        ];
    }

    public function uberConnection(): HasOne
    {
        return $this->hasOne(UberConnection::class);
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class);
    }

    public function rideOfferEvaluations(): HasMany
    {
        return $this->hasMany(RideOfferEvaluation::class)->latest('evaluated_at')->latest('id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function hasMobileApiToken(): bool
    {
        return filled($this->mobile_api_token_hash);
    }
}
