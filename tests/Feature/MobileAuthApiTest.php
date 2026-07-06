<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_returns_token_and_user_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'driver@example.com',
            'password' => 'password',
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
        ]);

        $response = $this->postJson(route('api.mobile.auth.login'), [
            'email' => 'driver@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('user.email', 'driver@example.com');
        $this->assertNotNull($user->fresh()->mobile_api_token_hash);
    }

    public function test_mobile_register_returns_token_and_creates_user(): void
    {
        $response = $this->postJson(route('api.mobile.auth.register'), [
            'name' => 'Motorista App',
            'email' => 'novo@example.com',
            'phone' => '(11) 99999-9999',
            'city' => 'Sao Paulo',
            'vehicle_type' => 'Carro',
            'work_shift' => 'Noite',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('user.email', 'novo@example.com');
        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
        ]);
    }
}
