<?php

namespace App\Support;

class MobileDecisionPayloadFactory
{
    public function build(array $analysis): array
    {
        $recommendation = (string) ($analysis['recommendation'] ?? 'risco_alto');
        $label = (string) ($analysis['recommendation_label'] ?? 'Risco alto');
        $score = (int) ($analysis['decision_score'] ?? 0);
        $riskLevel = (string) ($analysis['risk_level'] ?? 'medium');
        $zone = $analysis['matched_zone'] ?? $analysis['offer']['destination_zone_name'] ?? null;
        $netFare = $analysis['net']['net_fare'] ?? null;
        $fare = $analysis['offer']['quoted_fare'] ?? null;

        $tone = match ($recommendation) {
            'vale_a_pena' => 'positive',
            'regiao_destino_ruim' => 'warning',
            default => 'danger',
        };

        $headline = match ($recommendation) {
            'vale_a_pena' => 'Aceite rapido',
            'regiao_destino_ruim' => 'Destino fraco',
            default => 'Nao compensa',
        };

        $message = collect([
            $netFare !== null ? 'R$ '.number_format((float) $netFare, 2, ',', '.').' líquido' : ($fare ? 'R$ '.number_format((float) $fare, 2, ',', '.') : null),
            $zone ? 'Destino '.$zone : null,
            'Score '.$score,
        ])->filter()->implode(' • ');

        return [
            'listener_contract_version' => 1,
            'overlay' => [
                'show' => true,
                'tone' => $tone,
                'title' => 'Rota de Pico',
                'headline' => $headline,
                'label' => $label,
                'message' => $message !== '' ? $message : 'Decisao calculada em tempo real.',
                'score' => $score,
                'risk_level' => $riskLevel,
                'matched_zone' => $zone,
                'dismiss_after_ms' => 8000,
                'vibrate' => true,
                'sound' => match ($tone) {
                    'positive' => 'success',
                    'warning' => 'warning',
                    default => 'alert',
                },
            ],
            'push_notification' => [
                'title' => 'Rota de Pico: '.$label,
                'body' => $message !== '' ? $message : 'Abra o app para ver a analise da corrida.',
            ],
        ];
    }
}
