<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Support\ProductionReadiness;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:production-check', function (ProductionReadiness $readiness) {
    $report = $readiness->report();

    $this->newLine();
    $this->components->twoColumnDetail('Pronto para producao', $report['ready'] ? 'sim' : 'nao');
    $this->components->twoColumnDetail('Checks OK', (string) $report['summary']['ok']);
    $this->components->twoColumnDetail('Warnings', (string) $report['summary']['warnings']);
    $this->components->twoColumnDetail('Errors', (string) $report['summary']['errors']);
    $this->newLine();

    foreach ($report['checks'] as $check) {
        $icon = match ($check['status']) {
            'ok' => 'OK',
            'warning' => 'WARN',
            default => 'ERR',
        };

        $this->line("[{$icon}] {$check['label']} - {$check['message']}");
    }
})->purpose('Audit the environment and integrations for production readiness');

Artisan::command('app:deploy-checklist', function () {
    $steps = [
        '1. cp .env.production.example .env',
        '2. preencher credenciais reais de MySQL, Asaas e Uber',
        '3. php artisan key:generate',
        '4. php artisan migrate --force',
        '5. php artisan optimize',
        '6. configurar queue worker',
        '7. configurar webhook da Asaas',
        '8. validar callback da Uber',
        '9. php artisan app:production-check',
        '10. validar onboarding, assinatura e dashboard com usuario real',
    ];

    $this->newLine();
    $this->info('Checklist de deploy do Rotadepico');
    $this->newLine();

    foreach ($steps as $step) {
        $this->line($step);
    }
})->purpose('Show the production deploy checklist');
