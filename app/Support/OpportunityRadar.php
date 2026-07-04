<?php

namespace App\Support;

use App\Models\Opportunity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OpportunityRadar
{
    public function buildForUser(User $user, Collection $opportunities): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        $scored = $opportunities
            ->map(fn (Opportunity $opportunity) => $this->scoreOpportunity($opportunity, $user, $now))
            ->sortByDesc('predicted_score')
            ->values();

        $bestNow = $scored->first();
        $peakWindows = $scored->sortByDesc('expected_hourly')->take(3)->values();
        $heatZones = $scored->take(4)->values();
        $nextMoves = $scored->take(3)->map(function (array $zone, int $index) {
            return [
                'title' => match ($index) {
                    0 => 'Entrar agora',
                    1 => 'Segurar como plano B',
                    default => 'Observar antes de migrar',
                },
                'zone_name' => $zone['zone_name'],
                'window' => $zone['best_window'],
                'reason' => $zone['recommendation'],
            ];
        })->values();

        $mapZones = $scored->map(fn (array $zone) => [
            'zone_name' => $zone['zone_name'],
            'predicted_score' => $zone['predicted_score'],
            'fit_score' => $zone['fit_score'],
            'avg_fare' => $zone['avg_fare'],
            'best_window' => $zone['best_window'],
            'latitude' => $zone['latitude'],
            'longitude' => $zone['longitude'],
            'trend' => $zone['trend'],
            'route_profile' => $zone['route_profile'],
            'recommendation' => $zone['recommendation'],
            'pay_label' => $zone['pay_label'],
            'hotspot_radius_m' => $zone['hotspot_radius_m'],
            'pay_intensity' => $zone['pay_intensity'],
        ])->filter(fn (array $zone) => $zone['latitude'] && $zone['longitude'])->values();

        $topPayingRegions = $scored
            ->sortByDesc('avg_fare')
            ->take(4)
            ->values()
            ->map(fn (array $zone) => [
                'zone_name' => $zone['zone_name'],
                'avg_fare' => $zone['avg_fare'],
                'predicted_score' => $zone['predicted_score'],
                'pay_label' => $zone['pay_label'],
                'best_window' => $zone['best_window'],
                'route_profile' => $zone['route_profile'],
                'latitude' => $zone['latitude'],
                'longitude' => $zone['longitude'],
            ]);

        return [
            'bestNow' => $bestNow,
            'peakWindows' => $peakWindows,
            'heatZones' => $heatZones,
            'nextMoves' => $nextMoves,
            'mapZones' => $mapZones,
            'topPayingRegions' => $topPayingRegions,
            'stats' => [
                'avg_ticket' => round((float) $opportunities->avg('avg_fare'), 2),
                'best_score' => $bestNow['predicted_score'] ?? 0,
                'zones_online' => $opportunities->count(),
                'fit_average' => round($scored->avg('fit_score') ?? 0),
            ],
        ];
    }

    private function scoreOpportunity(Opportunity $opportunity, User $user, CarbonImmutable $now): array
    {
        $vehicleMatch = in_array($user->vehicle_type, $opportunity->preferred_vehicle_types ?? [], true);
        $shiftMatch = in_array($user->work_shift, $opportunity->preferred_shifts ?? [], true);
        $windowHot = $this->isInsideWindow($now, $opportunity->best_start_at, $opportunity->best_end_at);

        $fareScore = min(100, (float) $opportunity->avg_fare * 1.55);
        $supplyScore = max(0, 100 - ((float) $opportunity->active_driver_ratio * 100));
        $queueScore = max(0, 100 - (($opportunity->queue_pressure ?? 50) * 1.1));

        $predictedScore = (
            ($opportunity->score * 0.36) +
            ($fareScore * 0.18) +
            ($supplyScore * 0.16) +
            ($queueScore * 0.10) +
            (($vehicleMatch ? 100 : 48) * 0.10) +
            (($shiftMatch ? 100 : 52) * 0.07) +
            (($windowHot ? 100 : 60) * 0.03)
        );

        $fitScore = round((($vehicleMatch ? 1 : 0.55) + ($shiftMatch ? 1 : 0.55) + ($windowHot ? 1 : 0.7)) / 3 * 100);
        $expectedHourly = round((float) $opportunity->avg_fare * (1 + ($opportunity->score / 100)) * (1 - ((float) $opportunity->active_driver_ratio / 2)), 2);
        $payIntensity = (int) max(35, min(100, round((((float) $opportunity->avg_fare - 20) / 35) * 100)));
        $hotspotRadius = 550 + ($opportunity->score * 7);
        $payLabel = match (true) {
            (float) $opportunity->avg_fare >= 45 => 'Premium',
            (float) $opportunity->avg_fare >= 35 => 'Forte',
            (float) $opportunity->avg_fare >= 28 => 'Boa',
            default => 'Volume',
        };

        return [
            'zone_name' => $opportunity->zone_name,
            'predicted_score' => (int) round($predictedScore),
            'fit_score' => $fitScore,
            'avg_fare' => (float) $opportunity->avg_fare,
            'best_window' => "{$opportunity->best_start_at} - {$opportunity->best_end_at}",
            'active_driver_ratio' => (float) $opportunity->active_driver_ratio,
            'latitude' => $opportunity->latitude ? (float) $opportunity->latitude : null,
            'longitude' => $opportunity->longitude ? (float) $opportunity->longitude : null,
            'surge_label' => $opportunity->surge_label,
            'demand_level' => $opportunity->demand_level,
            'pickup_hotspot' => $opportunity->pickup_hotspot,
            'tip' => $opportunity->tip,
            'trend' => $opportunity->trend,
            'route_profile' => $opportunity->route_profile,
            'queue_pressure' => $opportunity->queue_pressure,
            'pay_label' => $payLabel,
            'hotspot_radius_m' => $hotspotRadius,
            'pay_intensity' => $payIntensity,
            'vehicle_match' => $vehicleMatch,
            'shift_match' => $shiftMatch,
            'window_hot' => $windowHot,
            'expected_hourly' => $expectedHourly,
            'recommendation' => $this->buildRecommendation($opportunity, $vehicleMatch, $shiftMatch, $windowHot),
            'signals' => array_values(array_filter([
                $vehicleMatch ? 'combina com seu veiculo' : 'menos aderente ao seu veiculo',
                $shiftMatch ? 'encaixa no seu turno' : 'melhor para outro turno',
                $windowHot ? 'janela aberta agora' : 'janela abrindo em breve',
            ])),
        ];
    }

    private function buildRecommendation(Opportunity $opportunity, bool $vehicleMatch, bool $shiftMatch, bool $windowHot): string
    {
        $parts = [];

        $parts[] = $vehicleMatch ? 'Boa aderencia ao seu tipo de veiculo.' : 'Funciona, mas nao e a melhor zona para seu veiculo.';
        $parts[] = $shiftMatch ? 'Seu turno encaixa com o padrao de demanda.' : 'Vale usar mais como zona de transicao.';
        $parts[] = $windowHot ? 'Janela operacional aberta agora.' : "Fique atento para entrar perto de {$opportunity->best_start_at}.";

        return implode(' ', $parts);
    }

    private function isInsideWindow(CarbonImmutable $now, string $startAt, string $endAt): bool
    {
        $start = CarbonImmutable::createFromFormat('H:i', $startAt, $now->timezone)->setDate($now->year, $now->month, $now->day);
        $end = CarbonImmutable::createFromFormat('H:i', $endAt, $now->timezone)->setDate($now->year, $now->month, $now->day);
        $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
        $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');

        if ($endMinutes < $startMinutes) {
            return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
        }

        return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
    }
}
