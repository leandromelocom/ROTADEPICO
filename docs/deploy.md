# Deploy de producao

## 1. Preparacao do servidor

- PHP 8.4+
- Composer
- MySQL 8+
- Nginx ou Apache
- Supervisor ou systemd para `queue:work`
- HTTPS com certificado valido

## 2. Variaveis de ambiente

Use o arquivo [.env.production.example](/home/administrator/Área%20de%20trabalho/Projetos/Uber99/.env.production.example) como base.

Pontos obrigatorios:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` com HTTPS
- `DB_CONNECTION=mysql`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`
- credenciais reais de `ASAAS`
- credenciais aprovadas de `UBER`

## 3. Primeiro provisionamento

```bash
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php artisan key:generate
php artisan migrate --force
php artisan optimize
php artisan storage:link
php artisan app:production-check
```

## 4. Queue worker

Exemplo com Supervisor:

```ini
[program:rotadepico-worker]
command=php /var/www/rotadepico/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/rotadepico
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/rotadepico/storage/logs/worker.log
stopwaitsecs=3600
```

## 5. Webhooks externos

Asaas:
- `POST https://SEU-DOMINIO/webhooks/asaas`
- configurar `asaas-access-token` igual a `ASAAS_WEBHOOK_TOKEN`

Uber:
- callback deve apontar para:
- `https://SEU-DOMINIO/integrations/uber/callback`

## 6. Publicacao de nova versao

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
php artisan app:production-check
```

## 7. Validacao final

- login funcionando
- onboarding abrindo sem erro
- checkout da Asaas abrindo
- webhook da Asaas confirmando assinatura
- dashboard liberando e bloqueando corretamente
- painel admin carregando
- comando `php artisan test` verde em homologacao antes de publicar
