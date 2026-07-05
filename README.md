# Rotadepico

Plataforma SaaS para motoristas de aplicativo com:
- onboarding self-service
- assinatura recorrente via Asaas
- integracao Uber
- radar de regioes quentes
- geolocalizacao em tempo real
- painel administrativo

## Stack

- Laravel 13
- Blade
- MySQL ou SQLite para desenvolvimento local
- JavaScript
- Asaas para cobranca recorrente
- Uber Drivers API para sincronizacao

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8080
```

## Variaveis importantes

```env
APP_ENV=production
APP_URL=https://seu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rotadepico
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ASAAS_API_KEY=
ASAAS_API_URL=https://api.asaas.com
ASAAS_WEBHOOK_TOKEN=

UBER_CLIENT_ID=
UBER_CLIENT_SECRET=
UBER_REDIRECT_URI=https://seu-dominio.com/integrations/uber/callback
```

## Comandos operacionais

Rodar testes:
```bash
php artisan test
```

Auditar prontidao para producao:
```bash
php artisan app:production-check
```

Ver checklist de deploy:
```bash
php artisan app:deploy-checklist
```

O comando valida:
- `APP_KEY`
- `APP_URL`
- conexao de banco
- session/cache/queue
- tabelas operacionais
- credenciais Asaas
- credenciais Uber

## Checklist de producao

1. Configurar `APP_ENV=production`
2. Configurar `APP_URL` com HTTPS
3. Migrar para `MySQL`
4. Executar `php artisan migrate --force`
5. Executar `php artisan optimize`
6. Configurar worker de fila
7. Configurar webhook da Asaas em `/webhooks/asaas`
8. Configurar callback da Uber
9. Executar `php artisan app:production-check`

Runbook detalhado:
- [docs/deploy.md](/home/administrator/Área%20de%20trabalho/Projetos/Uber99/docs/deploy.md)

## Painel admin

O painel administrativo mostra:
- base de motoristas
- receita mensal
- assinaturas ativas e inadimplentes
- status de onboarding
- status de conexao Uber
- prontidao de producao do ambiente
