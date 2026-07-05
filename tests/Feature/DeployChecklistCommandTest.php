<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployChecklistCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_checklist_command_prints_the_expected_steps(): void
    {
        $this->artisan('app:deploy-checklist')
            ->expectsOutputToContain('Checklist de deploy do Rotadepico')
            ->expectsOutputToContain('.env.production.example')
            ->expectsOutputToContain('php artisan app:production-check')
            ->assertExitCode(0);
    }
}
