# Android Companion Setup

O projeto Android nativo foi iniciado em [android-companion](/home/administrator/Área%20de%20trabalho/Projetos/Uber99/android-companion).

## O que este app faz

- escuta notificacoes da Uber Driver via `NotificationListenerService`
- envia o texto da oferta para o backend do Rotadepico
- recebe `vale a pena`, `nao compensa` ou `destino ruim`
- mostra overlay no topo da tela e heads-up notification

## Estrutura principal

- `MainActivity`
  tela de configuracao com URL base, token e status de permissoes

- `UberNotificationListenerService`
  captura notificacoes do pacote `com.ubercab.driver`

- `DecisionApiClient`
  envia o payload para `/api/mobile/listener/uber-offers/decision`

- `DecisionOverlayPresenter`
  exibe overlay e notificacao local com a decisao

## Como abrir no Android Studio

1. Abra a pasta `android-companion`.
2. Aguarde o sync do Gradle.
3. Ajuste a URL base para seu dominio final.
4. Gere o token mobile no perfil do motorista.
5. Cole o token no app companion.
6. Libere acesso a notificacoes e overlay.

## Limites desta primeira versao

- ainda nao existe tela de historico local
- ainda nao existe assinatura de dispositivo no backend
- ainda nao existe deduplicacao por `external_offer_id`
- fallback visual ainda e simples

## Proxima camada recomendada

- criptografar token com `EncryptedSharedPreferences`
- adicionar log local das ultimas corridas analisadas
- suportar 99 como segundo provider
- criar build debug e release com flavor
