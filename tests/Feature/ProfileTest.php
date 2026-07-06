<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_user_can_update_their_decision_settings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile/decision-settings', [
                'decision_profile' => 'premium',
                'min_offer_fare' => 35.00,
                'min_fare_per_km' => 3.80,
                'min_hourly_rate' => 52.00,
                'max_pickup_distance_km' => 2.20,
                'max_pickup_eta_minutes' => 5,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('premium', $user->decision_profile);
        $this->assertSame(35.0, $user->min_offer_fare);
        $this->assertSame(3.8, $user->min_fare_per_km);
        $this->assertSame(52.0, $user->min_hourly_rate);
        $this->assertSame(2.2, $user->max_pickup_distance_km);
        $this->assertSame(5, $user->max_pickup_eta_minutes);
    }
}
