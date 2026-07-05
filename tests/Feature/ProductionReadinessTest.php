<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_displays_production_readiness_section(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Prontidao de producao');
        $response->assertSee('APP_KEY');
        $response->assertSee('QUEUE_TABLES');
    }

    public function test_production_check_command_reports_summary(): void
    {
        Config::set('app.key', 'base64:test-key');
        Config::set('app.url', 'https://rotadepico.test');
        Config::set('session.driver', 'database');
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');

        $this->artisan('app:production-check')
            ->expectsOutputToContain('Pronto para producao')
            ->expectsOutputToContain('APP_KEY')
            ->expectsOutputToContain('QUEUE_TABLES')
            ->assertExitCode(0);
    }
}
