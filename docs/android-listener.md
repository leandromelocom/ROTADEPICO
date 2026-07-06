# Android Listener Companion

Esta integracao existe para tirar o preenchimento manual do motorista. O fluxo correto do companion app Android e:

1. O motorista recebe a notificacao da Uber.
2. Um `NotificationListenerService` do app Rotadepico le `packageName`, `title`, `text` e `postTime`.
3. O app envia esses dados para a API mobile com `Bearer token`.
4. O backend analisa a corrida e responde com `overlay` e `push_notification`.
5. O app mostra na mesma hora um overlay curto: `vale a pena`, `nao compensa` ou `destino ruim`.

## Permissoes Android

- `android.permission.POST_NOTIFICATIONS`
- acesso a notificacoes via `NotificationListenerService`
- opcional depois: `SYSTEM_ALERT_WINDOW` para overlay fora do app

## Endpoint principal

- `POST /api/mobile/listener/uber-offers/decision`
- autenticacao: `Authorization: Bearer <mobile_token>`
- content-type: `application/json`

## Payload minimo

```json
{
  "provider": "uber",
  "source": "notification_listener",
  "package_name": "com.ubercab.driver",
  "notification_title": "Uber",
  "notification_text": "Uber: R$ 48,90, embarque a 4 min, a 1,2 km, destino Zona Sul Premium, 1,4x",
  "notification_received_at": "2026-07-06T18:42:10-03:00",
  "device_id": "pixel-7-leandro"
}
```

## Resposta esperada

```json
{
  "recommendation": "vale_a_pena",
  "recommendation_label": "Vale a pena",
  "decision_score": 86,
  "risk_level": "low",
  "matched_zone": "Zona Sul Premium",
  "overlay": {
    "show": true,
    "tone": "positive",
    "title": "Rota de Pico",
    "headline": "Aceite rapido",
    "label": "Vale a pena",
    "message": "R$ 48,90 • Destino Zona Sul Premium • Score 86",
    "dismiss_after_ms": 8000,
    "sound": "success"
  },
  "push_notification": {
    "title": "Rota de Pico: Vale a pena",
    "body": "R$ 48,90 • Destino Zona Sul Premium • Score 86"
  }
}
```

## Estrutura sugerida no app Android

- `NotificationListenerService`
  Responsavel por filtrar apenas notificacoes da Uber Driver e montar o payload.

- `OfferDecisionRepository`
  Responsavel por chamar a API do Rotadepico.

- `OverlayDecisionService`
  Responsavel por exibir bolha, card flutuante ou heads-up notification com a resposta.

- `TokenStorage`
  Responsavel por guardar o Bearer token gerado no perfil do motorista.

## Regras praticas

- Ignorar notificacoes sem texto.
- Debounce de 2 a 3 segundos para nao analisar a mesma oferta varias vezes.
- Se houver `external_offer_id`, reaproveitar para deduplicacao local.
- Se a API falhar, mostrar fallback discreto: `Analise indisponivel`.

## Resultado desta fase

Com isso, o backend ja fica pronto para o app Android native listener. O passo seguinte e criar o projeto Android para consumir esse contrato.
