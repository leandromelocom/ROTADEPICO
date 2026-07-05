<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadiness
{
    public function report(): array
    {
        $checks = [
            $this->checkAppEnvironment(),
            $this->checkAppKey(),
            $this->checkAppDebug(),
            $this->checkAppUrl(),
            $this->checkDatabase(),
            $this->checkSessionDriver(),
            $this->checkSessionCookieSecurity(),
            $this->checkCacheStore(),
            $this->checkQueueConnection(),
            $this->checkQueueTables(),
            $this->checkAsaas(),
            $this->checkUber(),
        ];

        $ready = collect($checks)->every(fn (array $item) => $item['status'] === 'ok');
        $ok = collect($checks)->where('status', 'ok')->count();
        $warnings = collect($checks)->where('status', 'warning')->count();
        $errors = collect($checks)->where('status', 'error')->count();

        return [
            'ready' => $ready,
            'summary' => [
                'ok' => $ok,
                'warnings' => $warnings,
                'errors' => $errors,
            ],
            'checks' => $checks,
        ];
    }

    private function checkAppEnvironment(): array
    {
        $environment = (string) config('app.env');

        return $environment === 'production'
            ? $this->ok('APP_ENV', 'Aplicacao marcada como production.')
            : $this->warning('APP_ENV', "Ambiente atual {$environment}. Ajuste para production no deploy real.");
    }

    private function checkAppKey(): array
    {
        return filled(config('app.key'))
            ? $this->ok('APP_KEY', 'Chave da aplicacao configurada.')
            : $this->error('APP_KEY', 'Defina APP_KEY antes de subir o ambiente.');
    }

    private function checkAppDebug(): array
    {
        return config('app.debug') === false
            ? $this->ok('APP_DEBUG', 'Debug desativado.')
            : $this->warning('APP_DEBUG', 'Desative APP_DEBUG em producao.');
    }

    private function checkAppUrl(): array
    {
        $appUrl = (string) config('app.url');

        if (! filled($appUrl)) {
            return $this->error('APP_URL', 'APP_URL nao esta configurada.');
        }

        return str_starts_with($appUrl, 'https://')
            ? $this->ok('APP_URL', "URL principal configurada em {$appUrl}.")
            : $this->warning('APP_URL', "Use HTTPS em producao. Atual atual: {$appUrl}.");
    }

    private function checkDatabase(): array
    {
        $connection = (string) config('database.default');

        return $connection !== 'sqlite'
            ? $this->ok('DB_CONNECTION', "Banco principal usando {$connection}.")
            : $this->warning('DB_CONNECTION', 'O ambiente ainda usa sqlite. Feche MySQL para producao.');
    }

    private function checkSessionDriver(): array
    {
        $driver = (string) config('session.driver');

        return in_array($driver, ['database', 'redis'], true)
            ? $this->ok('SESSION_DRIVER', "Sessao usando {$driver}.")
            : $this->warning('SESSION_DRIVER', "Driver atual {$driver}. Prefira database ou redis.");
    }

    private function checkSessionCookieSecurity(): array
    {
        $secure = (bool) config('session.secure');

        return $secure
            ? $this->ok('SESSION_SECURE_COOKIE', 'Cookie seguro habilitado.')
            : $this->warning('SESSION_SECURE_COOKIE', 'Ative SESSION_SECURE_COOKIE=true em producao.');
    }

    private function checkCacheStore(): array
    {
        $store = (string) config('cache.default');

        return in_array($store, ['database', 'redis'], true)
            ? $this->ok('CACHE_STORE', "Cache usando {$store}.")
            : $this->warning('CACHE_STORE', "Store atual {$store}. Prefira database ou redis.");
    }

    private function checkQueueConnection(): array
    {
        $connection = (string) config('queue.default');

        return in_array($connection, ['database', 'redis', 'sqs'], true)
            ? $this->ok('QUEUE_CONNECTION', "Fila usando {$connection}.")
            : $this->warning('QUEUE_CONNECTION', "Conexao atual {$connection}. Prefira database, redis ou sqs.");
    }

    private function checkQueueTables(): array
    {
        try {
            $requiredTables = collect(['jobs', 'failed_jobs', 'sessions', 'cache']);
            $missing = $requiredTables->filter(fn (string $table) => ! Schema::hasTable($table))->values();
        } catch (Throwable $exception) {
            return $this->warning('QUEUE_TABLES', 'Nao foi possivel validar as tabelas operacionais com a conexao atual.');
        }

        return $missing->isEmpty()
            ? $this->ok('QUEUE_TABLES', 'Tabelas operacionais de fila, sessao e cache estao prontas.')
            : $this->warning('QUEUE_TABLES', 'Faltam tabelas: '.$missing->implode(', ').'.');
    }

    private function checkAsaas(): array
    {
        $apiKey = config('services.asaas.api_key');
        $apiUrl = config('services.asaas.api_url');
        $webhookToken = config('services.asaas.webhook_token');

        return filled($apiKey) && filled($apiUrl) && filled($webhookToken)
            ? $this->ok('ASAAS', 'Credenciais e token de webhook da Asaas configurados.')
            : $this->warning('ASAAS', 'Configure api key, api url e webhook token da Asaas.');
    }

    private function checkUber(): array
    {
        $clientId = config('services.uber.client_id');
        $clientSecret = config('services.uber.client_secret');
        $redirect = config('services.uber.redirect');

        return filled($clientId) && filled($clientSecret) && filled($redirect)
            ? $this->ok('UBER', 'Credenciais principais da Uber configuradas.')
            : $this->warning('UBER', 'Configure client id, client secret e redirect da Uber.');
    }

    private function ok(string $label, string $message): array
    {
        return [
            'label' => $label,
            'status' => 'ok',
            'message' => $message,
        ];
    }

    private function warning(string $label, string $message): array
    {
        return [
            'label' => $label,
            'status' => 'warning',
            'message' => $message,
        ];
    }

    private function error(string $label, string $message): array
    {
        return [
            'label' => $label,
            'status' => 'error',
            'message' => $message,
        ];
    }
}
