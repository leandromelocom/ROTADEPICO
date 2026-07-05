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

        return $this->buildPayload($user, $opportunities, $scored);
    }

    public function buildLocalizedForUser(User $user, Collection $opportunities, float $latitude, float $longitude): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        $scored = $opportunities
            ->map(fn (Opportunity $opportunity) => $this->scoreOpportunity($opportunity, $user, $now, $latitude, $longitude))
            ->sortByDesc('localized_priority')
            ->values();

        return $this->buildPayload($user, $opportunities, $scored, $latitude, $longitude);
    }

    private function buildPayload(User $user, Collection $opportunities, Collection $scored, ?float $latitude = null, ?float $longitude = null): array
    {
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
                'distance_km' => $zone['distance_km'],
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
            'distance_km' => $zone['distance_km'],
            'localized_priority' => $zone['localized_priority'],
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
                'distance_km' => $zone['distance_km'],
            ]);

        $nearbyComparisons = $scored
            ->filter(fn (array $zone) => $zone['distance_km'] !== null)
            ->sortBy('distance_km')
            ->take(3)
            ->values()
            ->map(fn (array $zone) => [
                'zone_name' => $zone['zone_name'],
                'distance_km' => $zone['distance_km'],
                'avg_fare' => $zone['avg_fare'],
                'predicted_score' => $zone['predicted_score'],
                'localized_priority' => $zone['localized_priority'],
                'reason' => $zone['comparison_reason'],
                'best_window' => $zone['best_window'],
            ]);

        $hourlyRankings = collect([0, 60, 120, 180])
            ->map(fn (int $offset) => $this->buildHourlyRanking($scored, $offset))
            ->values();

        $shiftForecasts = collect([
            $this->buildShiftForecast($scored, 'Manha'),
            $this->buildShiftForecast($scored, 'Tarde'),
            $this->buildShiftForecast($scored, 'Noite'),
            $this->buildShiftForecast($scored, 'Madrugada'),
        ])->filter()->values();

        return [
            'bestNow' => $bestNow,
            'peakWindows' => $peakWindows,
            'heatZones' => $heatZones,
            'nextMoves' => $nextMoves,
            'mapZones' => $mapZones,
            'topPayingRegions' => $topPayingRegions,
            'nearbyComparisons' => $nearbyComparisons,
            'hourlyRankings' => $hourlyRankings,
            'shiftForecasts' => $shiftForecasts,
            'driverLocation' => $latitude !== null && $longitude !== null ? [
                'latitude' => round($latitude, 6),
                'longitude' => round($longitude, 6),
            ] : null,
            'stats' => [
                'avg_ticket' => round((float) $opportunities->avg('avg_fare'), 2),
                'best_score' => $bestNow['predicted_score'] ?? 0,
                'zones_online' => $opportunities->count(),
                'fit_average' => round($scored->avg('fit_score') ?? 0),
                'closest_best_distance_km' => $bestNow['distance_km'] !== null ? round($bestNow['distance_km'], 1) : null,
                'localized_mode' => $latitude !== null && $longitude !== null,
            ],
        ];
    }

    private function scoreOpportunity(Opportunity $opportunity, User $user, CarbonImmutable $now, ?float $latitude = null, ?float $longitude = null): array
    {
        $vehicleMatch = in_array($user->vehicle_type, $opportunity->preferred_vehicle_types ?? [], true);
        $shiftMatch = in_array($user->work_shift, $opportunity->preferred_shifts ?? [], true);
        $windowHot = $this->isInsideWindow($now, $opportunity->best_start_at, $opportunity->best_end_at);
        $timingScore = $this->timingScore($now, $opportunity->best_start_at, $opportunity->best_end_at);

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

        $distanceKm = $latitude !== null && $longitude !== null && $opportunity->latitude && $opportunity->longitude
            ? $this->haversineKm($latitude, $longitude, (float) $opportunity->latitude, (float) $opportunity->longitude)
            : null;

        $distancePenalty = $distanceKm !== null ? min($distanceKm * 3.2, 34) : 0;
        $localizedPriority = round($predictedScore + ($timingScore * 0.22) - $distancePenalty);

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
            'localized_priority' => (int) max(0, min(100, $localizedPriority)),
            'fit_score' => $fitScore,
            'avg_fare' => (float) $opportunity->avg_fare,
            'best_window' => "{$opportunity->best_start_at} - {$opportunity->best_end_at}",
            'active_driver_ratio' => (float) $opportunity->active_driver_ratio,
            'latitude' => $opportunity->latitude ? (float) $opportunity->latitude : null,
            'longitude' => $opportunity->longitude ? (float) $opportunity->longitude : null,
            'distance_km' => $distanceKm !== null ? round($distanceKm, 1) : null,
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
            'timing_score' => $timingScore,
            'expected_hourly' => $expectedHourly,
            'recommendation' => $this->buildRecommendation($opportunity, $vehicleMatch, $shiftMatch, $windowHot, $distanceKm, $timingScore),
            'comparison_reason' => $this->buildComparisonReason($distanceKm, $timingScore, $expectedHourly),
            'signals' => array_values(array_filter([
                $vehicleMatch ? 'combina com seu veiculo' : 'menos aderente ao seu veiculo',
                $shiftMatch ? 'encaixa no seu turno' : 'melhor para outro turno',
                $windowHot ? 'janela aberta agora' : 'janela abrindo em breve',
                $distanceKm !== null ? "a {$distanceKm} km de voce" : null,
            ])),
            'preferred_shifts' => $opportunity->preferred_shifts ?? [],
        ];
    }

    private function buildRecommendation(Opportunity $opportunity, bool $vehicleMatch, bool $shiftMatch, bool $windowHot, ?float $distanceKm, float $timingScore): string
    {
        $parts = [];

        $parts[] = $vehicleMatch ? 'Boa aderencia ao seu tipo de veiculo.' : 'Funciona, mas nao e a melhor zona para seu veiculo.';
        $parts[] = $shiftMatch ? 'Seu turno encaixa com o padrao de demanda.' : 'Vale usar mais como zona de transicao.';
        $parts[] = $windowHot ? 'Janela operacional aberta agora.' : "Fique atento para entrar perto de {$opportunity->best_start_at}.";
        $parts[] = $distanceKm !== null
            ? ($distanceKm <= 3.5 ? 'Deslocamento curto para capturar giro rapido.' : "Exige deslocamento de {$distanceKm} km.")
            : 'Sem geolocalizacao ativa, recomendacao baseada em score puro.';
        $parts[] = $timingScore >= 88 ? 'Va agora para esta zona.' : 'Observe a transicao de horario antes de migrar.';

        return implode(' ', $parts);
    }

    private function buildComparisonReason(?float $distanceKm, float $timingScore, float $expectedHourly): string
    {
        if ($distanceKm === null) {
            return "Potencial de R$ {$expectedHourly}/h quando a localizacao for ativada.";
        }

        if ($distanceKm <= 2.5) {
            return "Muito perto de voce, com janela forte e potencial de R$ {$expectedHourly}/h.";
        }

        if ($timingScore >= 85) {
            return 'Vale o deslocamento porque o horario esta aquecendo agora.';
        }

        return 'Boa opcao de apoio, mas com deslocamento mais longo.';
    }

    private function buildHourlyRanking(Collection $scored, int $offsetMinutes): array
    {
        $label = match ($offsetMinutes) {
            0 => 'Agora',
            60 => '+1 hora',
            120 => '+2 horas',
            default => '+3 horas',
        };

        $zone = $scored
            ->sortByDesc(fn (array $zone) => $zone['timing_score'] - ($offsetMinutes / 18) + ($zone['expected_hourly'] / 10))
            ->first();

        return [
            'label' => $label,
            'zone_name' => $zone['zone_name'] ?? 'Sem leitura',
            'best_window' => $zone['best_window'] ?? '--',
            'predicted_score' => $zone['predicted_score'] ?? 0,
            'expected_hourly' => $zone['expected_hourly'] ?? 0,
        ];
    }

    private function buildShiftForecast(Collection $scored, string $shift): ?array
    {
        $zone = $scored
            ->filter(fn (array $zone) => in_array($shift, $zone['preferred_shifts'], true))
            ->sortByDesc('predicted_score')
            ->first();

        if (! $zone) {
            return null;
        }

        return [
            'shift' => $shift,
            'zone_name' => $zone['zone_name'],
            'expected_hourly' => $zone['expected_hourly'],
            'best_window' => $zone['best_window'],
            'reason' => $zone['recommendation'],
        ];
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

    private function timingScore(CarbonImmutable $now, string $startAt, string $endAt): float
    {
        if ($this->isInsideWindow($now, $startAt, $endAt)) {
            return 100;
        }

        $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
        $startMinutes = ((int) substr($startAt, 0, 2) * 60) + (int) substr($startAt, 3, 2);
        $difference = abs($startMinutes - $currentMinutes);

        return max(30, 100 - min($difference / 2, 70));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
