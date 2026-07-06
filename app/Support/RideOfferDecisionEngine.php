<?php

namespace App\Support;

use App\Models\DriverTrip;
use App\Models\Opportunity;
use App\Models\RideOfferEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RideOfferDecisionEngine
{
    public function __construct(private readonly DriverDecisionPreferences $preferences)
    {
    }

    public function analyze(User $user, array $payload): array
    {
        $opportunities = Opportunity::query()->get();
        $history = $user->driverTrips()
            ->whereNotNull('fare')
            ->latest('accepted_at')
            ->take(40)
            ->get();

        $matchedOpportunity = $this->matchDestinationOpportunity($payload, $opportunities);
        $historyMetrics = $this->buildHistoryMetrics($history, $opportunities);
        $preferences = $this->preferences->forUser($user);

        $quotedFare = (float) ($payload['quoted_fare'] ?? 0);
        $pickupDistanceKm = $this->floatOrNull($payload['pickup_distance_km'] ?? null);
        $tripDistanceKm = $this->floatOrNull($payload['trip_distance_km'] ?? null);
        $pickupEtaMinutes = $payload['pickup_eta_minutes'] ?? null;
        $surgeMultiplier = $this->floatOrNull($payload['surge_multiplier'] ?? null);

        $fareEfficiencyScore = $this->fareEfficiencyScore($quotedFare, $tripDistanceKm, $pickupDistanceKm, $historyMetrics);
        $pickupBurdenScore = $this->pickupBurdenScore($pickupDistanceKm, $pickupEtaMinutes);
        $destinationContext = $this->destinationContextScore($matchedOpportunity, $user);
        $historyFitScore = $this->historyFitScore($quotedFare, $historyMetrics['avg_fare']);
        $surgeBoostScore = $this->surgeBoostScore($surgeMultiplier);
        $projectedHourlyRate = $this->projectedHourlyRate($quotedFare, $tripDistanceKm, $pickupEtaMinutes);
        $offerPerKm = $this->offerPerKm($quotedFare, $tripDistanceKm, $pickupDistanceKm);

        $decisionScore = (int) round(
            ($fareEfficiencyScore * 0.34) +
            ($pickupBurdenScore * 0.20) +
            ($destinationContext['score'] * 0.24) +
            ($historyFitScore * 0.14) +
            ($surgeBoostScore * 0.08)
        );

        $decisionScore = max(0, min(100, $decisionScore));

        [$recommendation, $riskLevel] = $this->finalRecommendation(
            $decisionScore,
            $pickupBurdenScore,
            $destinationContext['risk'],
            $quotedFare,
            $offerPerKm,
            $projectedHourlyRate,
            $pickupDistanceKm,
            $pickupEtaMinutes,
            $preferences
        );

        $reasons = array_values(array_filter([
            $this->fareReason($quotedFare, $historyMetrics['avg_fare'], $fareEfficiencyScore),
            $this->pickupReason($pickupDistanceKm, $pickupEtaMinutes, $pickupBurdenScore),
            $this->destinationReason($matchedOpportunity, $destinationContext),
            $this->preferenceReason($quotedFare, $offerPerKm, $projectedHourlyRate, $pickupDistanceKm, $pickupEtaMinutes, $preferences),
            $surgeMultiplier !== null ? 'Multiplicador dinâmico detectado na oferta.' : null,
            $projectedHourlyRate !== null ? 'Projeção operacional de R$ '.number_format($projectedHourlyRate, 2, ',', '.').'/h se a corrida fechar como lida.' : null,
        ]));

        $result = [
            'recommendation' => $recommendation,
            'recommendation_label' => $this->recommendationLabel($recommendation),
            'decision_score' => $decisionScore,
            'risk_level' => $riskLevel,
            'destination_risk' => $destinationContext['risk'],
            'projected_hourly_rate' => $projectedHourlyRate,
            'matched_zone' => $matchedOpportunity?->zone_name,
            'destination_profile' => $matchedOpportunity?->route_profile,
            'destination_trend' => $matchedOpportunity?->trend,
            'reasons' => $reasons,
            'signals' => [
                'fare_efficiency_score' => $fareEfficiencyScore,
                'pickup_burden_score' => $pickupBurdenScore,
                'destination_score' => $destinationContext['score'],
                'history_fit_score' => $historyFitScore,
                'surge_boost_score' => $surgeBoostScore,
            ],
            'driver_preferences' => $preferences,
            'offer' => [
                'quoted_fare' => $quotedFare > 0 ? round($quotedFare, 2) : null,
                'pickup_distance_km' => $pickupDistanceKm,
                'trip_distance_km' => $tripDistanceKm,
                'pickup_eta_minutes' => $pickupEtaMinutes,
                'surge_multiplier' => $surgeMultiplier,
                'destination_zone_name' => $payload['destination_zone_name'] ?? null,
            ],
        ];

        $user->rideOfferEvaluations()->create([
            'provider' => (string) ($payload['provider'] ?? 'uber'),
            'source' => (string) ($payload['source'] ?? 'notification'),
            'external_offer_id' => $payload['external_offer_id'] ?? null,
            'quoted_fare' => $result['offer']['quoted_fare'],
            'currency_code' => (string) ($payload['currency_code'] ?? 'BRL'),
            'pickup_distance_km' => $pickupDistanceKm,
            'trip_distance_km' => $tripDistanceKm,
            'pickup_eta_minutes' => $pickupEtaMinutes,
            'surge_multiplier' => $surgeMultiplier,
            'destination_zone_name' => $payload['destination_zone_name'] ?? null,
            'destination_latitude' => $payload['destination_latitude'] ?? null,
            'destination_longitude' => $payload['destination_longitude'] ?? null,
            'decision_score' => $decisionScore,
            'recommendation' => $recommendation,
            'risk_level' => $riskLevel,
            'destination_risk' => $destinationContext['risk'],
            'matched_opportunity_zone' => $matchedOpportunity?->zone_name,
            'projected_hourly_rate' => $projectedHourlyRate,
            'reasons' => $reasons,
            'raw_payload' => $payload['raw_payload'] ?? $payload,
            'evaluated_at' => now(),
        ]);

        return $result;
    }

    private function offerPerKm(float $quotedFare, ?float $tripDistanceKm, ?float $pickupDistanceKm): ?float
    {
        if ($quotedFare <= 0) {
            return null;
        }

        $effectiveKm = max(1.0, ($tripDistanceKm ?? 0) + (($pickupDistanceKm ?? 0) * 0.55));

        return round($quotedFare / $effectiveKm, 2);
    }

    private function matchDestinationOpportunity(array $payload, Collection $opportunities): ?Opportunity
    {
        $latitude = $this->floatOrNull($payload['destination_latitude'] ?? null);
        $longitude = $this->floatOrNull($payload['destination_longitude'] ?? null);

        if ($latitude !== null && $longitude !== null) {
            return $opportunities
                ->filter(fn (Opportunity $opportunity): bool => $opportunity->latitude !== null && $opportunity->longitude !== null)
                ->sortBy(fn (Opportunity $opportunity): float => $this->haversineKm(
                    $latitude,
                    $longitude,
                    (float) $opportunity->latitude,
                    (float) $opportunity->longitude
                ))
                ->first();
        }

        $zoneName = Str::of((string) ($payload['destination_zone_name'] ?? ''))
            ->lower()
            ->ascii()
            ->trim()
            ->toString();

        if ($zoneName === '') {
            return null;
        }

        return $opportunities
            ->first(function (Opportunity $opportunity) use ($zoneName): bool {
                $candidate = Str::of($opportunity->zone_name)
                    ->lower()
                    ->ascii()
                    ->trim()
                    ->toString();

                return Str::contains($candidate, $zoneName) || Str::contains($zoneName, $candidate);
            });
    }

    private function buildHistoryMetrics(Collection $history, Collection $opportunities): array
    {
        $avgFare = round((float) ($history->avg('fare') ?? $opportunities->avg('avg_fare') ?? 0), 2);

        $farePerKm = $history
            ->map(function (DriverTrip $trip): ?float {
                $tripDistanceKm = $trip->distance_miles ? ((float) $trip->distance_miles * 1.60934) : null;

                if (! $tripDistanceKm || $tripDistanceKm <= 0 || ! $trip->fare) {
                    return null;
                }

                return (float) $trip->fare / $tripDistanceKm;
            })
            ->filter()
            ->avg();

        return [
            'avg_fare' => $avgFare > 0 ? $avgFare : 28.0,
            'avg_fare_per_km' => $farePerKm ? round((float) $farePerKm, 2) : 3.1,
        ];
    }

    private function fareEfficiencyScore(float $quotedFare, ?float $tripDistanceKm, ?float $pickupDistanceKm, array $historyMetrics): int
    {
        if ($quotedFare <= 0) {
            return 35;
        }

        $effectiveKm = max(1.0, ($tripDistanceKm ?? 0) + (($pickupDistanceKm ?? 0) * 0.55));
        $offerPerKm = $quotedFare / $effectiveKm;
        $ratio = $offerPerKm / max(1.0, (float) $historyMetrics['avg_fare_per_km']);

        return (int) max(15, min(100, round($ratio * 68)));
    }

    private function pickupBurdenScore(?float $pickupDistanceKm, ?int $pickupEtaMinutes): int
    {
        $distancePenalty = $pickupDistanceKm !== null ? ($pickupDistanceKm * 9) : 14;
        $etaPenalty = $pickupEtaMinutes !== null ? ($pickupEtaMinutes * 4) : 10;

        return (int) max(0, min(100, round(100 - $distancePenalty - $etaPenalty)));
    }

    private function destinationContextScore(?Opportunity $opportunity, User $user): array
    {
        if (! $opportunity) {
            return [
                'score' => 52,
                'risk' => 'medium',
            ];
        }

        $windowHot = $this->isInsideWindow(
            CarbonImmutable::now(config('app.timezone')),
            $opportunity->best_start_at,
            $opportunity->best_end_at
        );

        $vehicleMatch = in_array($user->vehicle_type, $opportunity->preferred_vehicle_types ?? [], true);
        $shiftMatch = in_array($user->work_shift, $opportunity->preferred_shifts ?? [], true);

        $score = (int) round(
            ($opportunity->score * 0.52) +
            ((100 - ((float) $opportunity->active_driver_ratio * 100)) * 0.18) +
            ((100 - (($opportunity->queue_pressure ?? 50) * 0.9)) * 0.10) +
            (($windowHot ? 100 : 60) * 0.08) +
            (($vehicleMatch ? 100 : 55) * 0.06) +
            (($shiftMatch ? 100 : 55) * 0.06)
        );

        $risk = match (true) {
            $opportunity->score < 55,
            $opportunity->trend === 'descendo',
            ((float) $opportunity->active_driver_ratio) >= 0.82 => 'high',
            $opportunity->score < 70,
            ((float) $opportunity->active_driver_ratio) >= 0.62 => 'medium',
            default => 'low',
        };

        return [
            'score' => max(0, min(100, $score)),
            'risk' => $risk,
        ];
    }

    private function historyFitScore(float $quotedFare, float $averageFare): int
    {
        if ($quotedFare <= 0 || $averageFare <= 0) {
            return 45;
        }

        $ratio = $quotedFare / $averageFare;

        return (int) max(10, min(100, round($ratio * 62)));
    }

    private function surgeBoostScore(?float $surgeMultiplier): int
    {
        if ($surgeMultiplier === null) {
            return 50;
        }

        return (int) max(30, min(100, round($surgeMultiplier * 36)));
    }

    private function finalRecommendation(
        int $decisionScore,
        int $pickupBurdenScore,
        string $destinationRisk,
        float $quotedFare,
        ?float $offerPerKm,
        ?float $projectedHourlyRate,
        ?float $pickupDistanceKm,
        ?int $pickupEtaMinutes,
        array $preferences
    ): array
    {
        if ($destinationRisk === 'high' && $decisionScore < 68) {
            return ['regiao_destino_ruim', 'high'];
        }

        if (($pickupDistanceKm !== null && $pickupDistanceKm > $preferences['max_pickup_distance_km'])
            || ($pickupEtaMinutes !== null && $pickupEtaMinutes > $preferences['max_pickup_eta_minutes'])) {
            return ['nao_vale', 'high'];
        }

        if (($quotedFare > 0 && $quotedFare < $preferences['min_offer_fare'])
            || ($offerPerKm !== null && $offerPerKm < $preferences['min_fare_per_km'])
            || ($projectedHourlyRate !== null && $projectedHourlyRate < $preferences['min_hourly_rate'])) {
            return ['nao_vale', $destinationRisk === 'low' ? 'medium' : 'high'];
        }

        if ($decisionScore >= 72 && $pickupBurdenScore >= 45 && $destinationRisk !== 'high') {
            return ['vale_a_pena', 'low'];
        }

        if ($decisionScore < 50 || $pickupBurdenScore < 28) {
            return ['nao_vale', 'high'];
        }

        return ['risco_alto', $destinationRisk === 'low' ? 'medium' : 'high'];
    }

    private function preferenceReason(
        float $quotedFare,
        ?float $offerPerKm,
        ?float $projectedHourlyRate,
        ?float $pickupDistanceKm,
        ?int $pickupEtaMinutes,
        array $preferences
    ): ?string {
        if ($quotedFare > 0 && $quotedFare < $preferences['min_offer_fare']) {
            return 'A oferta ficou abaixo do valor minimo que voce definiu para hoje.';
        }

        if ($offerPerKm !== null && $offerPerKm < $preferences['min_fare_per_km']) {
            return 'O ganho por km ficou abaixo da sua meta configurada.';
        }

        if ($projectedHourlyRate !== null && $projectedHourlyRate < $preferences['min_hourly_rate']) {
            return 'A projeção por hora nao atingiu a sua meta operacional.';
        }

        if (($pickupDistanceKm !== null && $pickupDistanceKm > $preferences['max_pickup_distance_km'])
            || ($pickupEtaMinutes !== null && $pickupEtaMinutes > $preferences['max_pickup_eta_minutes'])) {
            return 'O embarque passou do limite maximo que voce aceitou configurar.';
        }

        return 'A corrida respeita a sua regua pessoal configurada no perfil.';
    }

    private function recommendationLabel(string $recommendation): string
    {
        return match ($recommendation) {
            'vale_a_pena' => 'Vale a pena',
            'nao_vale' => 'Nao vale',
            'regiao_destino_ruim' => 'Regiao de destino ruim',
            default => 'Risco alto',
        };
    }

    private function fareReason(float $quotedFare, float $averageFare, int $fareEfficiencyScore): ?string
    {
        if ($quotedFare <= 0) {
            return 'A notificacao ainda nao trouxe valor confiavel da corrida.';
        }

        if ($quotedFare >= $averageFare) {
            return "Oferta acima da sua media historica de R$ ".number_format($averageFare, 2, ',', '.').".";
        }

        if ($fareEfficiencyScore < 45) {
            return 'Valor fraco para a distancia estimada dessa corrida.';
        }

        return 'Ticket aceitavel, mas sem sobra forte sobre sua media.';
    }

    private function pickupReason(?float $pickupDistanceKm, ?int $pickupEtaMinutes, int $pickupBurdenScore): string
    {
        if ($pickupBurdenScore < 35) {
            return 'Deslocamento ate o embarque ficou pesado e consome giro do turno.';
        }

        if (($pickupDistanceKm ?? 0) <= 2.5 && ($pickupEtaMinutes ?? 99) <= 6) {
            return 'Embarque perto de voce, com boa chance de resposta rapida.';
        }

        return 'Embarque administravel, mas exige atencao para nao perder ritmo.';
    }

    private function destinationReason(?Opportunity $opportunity, array $destinationContext): string
    {
        if (! $opportunity) {
            return 'Destino ainda sem zona mapeada, leitura feita com risco intermediario.';
        }

        if ($destinationContext['risk'] === 'high') {
            return "Destino cai em {$opportunity->zone_name}, zona hoje com risco alto de retorno fraco.";
        }

        if ($destinationContext['risk'] === 'low') {
            return "Destino conversa bem com {$opportunity->zone_name}, que esta pagando com boa tracao agora.";
        }

        return "Destino em {$opportunity->zone_name}, com potencial mediano e exigindo selecao mais fria.";
    }

    private function projectedHourlyRate(float $quotedFare, ?float $tripDistanceKm, ?int $pickupEtaMinutes): ?float
    {
        if ($quotedFare <= 0) {
            return null;
        }

        $tripMinutes = $tripDistanceKm !== null ? max(8.0, ($tripDistanceKm / 24) * 60) : 18.0;
        $pickupMinutes = $pickupEtaMinutes !== null ? max(3, $pickupEtaMinutes) : 6;
        $totalHours = max(0.25, ($pickupMinutes + $tripMinutes) / 60);

        return round($quotedFare / $totalHours, 2);
    }

    private function isInsideWindow(CarbonImmutable $now, ?string $start, ?string $end): bool
    {
        if (! $start || ! $end) {
            return false;
        }

        $today = $now->format('Y-m-d');
        $windowStart = CarbonImmutable::parse("{$today} {$start}", config('app.timezone'));
        $windowEnd = CarbonImmutable::parse("{$today} {$end}", config('app.timezone'));

        if ($windowEnd->lessThan($windowStart)) {
            $windowEnd = $windowEnd->addDay();
        }

        return $now->betweenIncluded($windowStart, $windowEnd);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $angle = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1, sqrt($angle)));
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
