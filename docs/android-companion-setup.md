# Android Companion Setup

O projeto Android nativo foi iniciado em [android-companion](/home/administrator/Área%20de%20trabalho/Projetos/Uber99/android-companion).

## O que este app faz

- escuta notificacoes da Uber Driver via `NotificationListenerService`
- envia o texto da oferta para o backend do Rotadepico
- recebe `vale a pena`, `nao compensa` ou `destino ruim`
- mostra overlay no topo da tela e heads-up notification
- guarda historico local das ultimas decisoes no proprio aparelho

## Estrutura principal

- `MainActivity`
  tela de configuracao com URL base, token e status de permissoes

- `UberNotificationListenerService`
  captura notificacoes do pacote `com.ubercab.driver`

- `DecisionApiClient`
  envia o payload para `/api/mobile/listener/uber-offers/decision`

- `DecisionOverlayPresenter`
  exibe overlay e notificacao local com a decisao

## Fluxo do motorista (sem copiar nada)

1. Instala o app Rotadepico Companion no celular.
2. Abre o app e faz login com o mesmo e-mail e senha da conta Rotadepico — o app busca o token mobile sozinho, sem precisar colar nada.
3. Libera acesso a notificacoes e overlay (botoes dentro do proprio app).
4. Abre o app oficial da Uber Driver normalmente.

A URL base ja vem com o dominio de producao como padrao. Trocar URL ou colar um Bearer token manualmente so e necessario em "Opcoes avancadas", pra troubleshooting ou quando o motorista prefere nao digitar a senha no aparelho.

## Como abrir no Android Studio

1. Abra a pasta `android-companion`.
2. Aguarde o sync do Gradle.
3. Rode em um emulador ou aparelho e faca login com uma conta de teste.

## Registro automatico do aparelho

Na primeira oferta enviada pelo listener, o backend registra automaticamente:

- `device_id`
- `device_label`
- `platform`
- `package_name`
- `app_version`
- ultimo horario de atividade

Isso aparece no perfil do motorista em "Dispositivos conectados".

## Limites desta primeira versao

- ainda nao existe assinatura de dispositivo no backend
- ainda nao existe deduplicacao por `external_offer_id`
- fallback visual ainda e simples

## Proxima camada recomendada

- criptografar token com `EncryptedSharedPreferences`
- adicionar log local das ultimas corridas analisadas
- suportar 99 como segundo provider
- criar build debug e release com flavor
