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
    public function __construct(
        private readonly DriverDecisionPreferences $preferences,
        private readonly VehicleOperatingCost $operatingCost,
    ) {
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
        $costPerKm = $this->operatingCost->costPerKm($user);

        $quotedFare = (float) ($payload['quoted_fare'] ?? 0);
        $pickupDistanceKm = $this->floatOrNull($payload['pickup_distance_km'] ?? null);
        $tripDistanceKmProvided = $this->floatOrNull($payload['trip_distance_km'] ?? null);
        $pickupEtaMinutes = $payload['pickup_eta_minutes'] ?? null;
        $surgeMultiplier = $this->floatOrNull($payload['surge_multiplier'] ?? null);

        // A notificacao nem sempre traz a distancia da viagem; sem isso o custo de
        // combustivel ficaria zerado por falta de dado, escondendo o gasto real do motorista.
        $costEstimated = $tripDistanceKmProvided === null || $pickupDistanceKm === null;
        $tripDistanceKm = $tripDistanceKmProvided ?? $historyMetrics['avg_trip_distance_km'];

        $destinationContext = $this->destinationContextScore($matchedOpportunity, $user);

        // Destino em zona fraca costuma exigir rodar vazio de volta para uma area viavel:
        // isso tambem consome combustivel e tempo, entao entra na conta como km extra.
        $returnLegKm = $destinationContext['risk'] === 'high' ? round($tripDistanceKm * 0.35, 2) : 0.0;

        $totalDrivingKm = $tripDistanceKm + ($pickupDistanceKm ?? 0) + $returnLegKm;
        $estimatedCost = $quotedFare > 0 ? round($totalDrivingKm * $costPerKm, 2) : 0.0;
        $netFare = $quotedFare > 0 ? max(0.0, round($quotedFare - $estimatedCost, 2)) : null;

        $effectiveKm = max(1.0, $tripDistanceKm + (($pickupDistanceKm ?? 0) * 0.55));
        $netFarePerKm = $netFare !== null ? round($netFare / $effectiveKm, 2) : null;

        $totalMinutes = $this->totalMinutes($tripDistanceKm, $pickupEtaMinutes, $returnLegKm);
        $totalHours = max(0.25, $totalMinutes / 60);
        $projectedHourlyRate = $quotedFare > 0 ? round($quotedFare / $totalHours, 2) : null;
        $netHourlyRate = $netFare !== null ? round($netFare / $totalHours, 2) : null;

        $avgNetFarePerKm = max(0.5, $historyMetrics['avg_fare_per_km'] - $costPerKm);

        $netEfficiencyScore = $this->netEfficiencyScore($quotedFare, $netFarePerKm, $avgNetFarePerKm);
        $pickupBurdenScore = $this->pickupBurdenScore($pickupDistanceKm, $pickupEtaMinutes);
        $netHourlyScore = $this->netHourlyScore($netHourlyRate, $preferences['min_hourly_rate']);

        $decisionScore = (int) round(
            ($netEfficiencyScore * 0.30) +
            ($pickupBurdenScore * 0.16) +
            ($destinationContext['score'] * 0.24) +
            ($netHourlyScore * 0.30)
        );

        $decisionScore = max(0, min(100, $decisionScore));

        [$recommendation, $riskLevel] = $this->finalRecommendation(
            $decisionScore,
            $pickupBurdenScore,
            $destinationContext['risk'],
            $quotedFare,
            $netFarePerKm,
            $netHourlyRate,
            $pickupDistanceKm,
            $pickupEtaMinutes,
            $preferences
        );

        $reasons = array_values(array_filter([
            $this->netFareReason($quotedFare, $estimatedCost, $netFare, $netHourlyRate, $costEstimated),
            $this->pickupReason($pickupDistanceKm, $pickupEtaMinutes, $pickupBurdenScore),
            $this->destinationReason($matchedOpportunity, $destinationContext),
            $this->preferenceReason($quotedFare, $netFarePerKm, $netHourlyRate, $pickupDistanceKm, $pickupEtaMinutes, $preferences),
            $surgeMultiplier !== null ? 'Multiplicador dinâmico já incluído no valor cotado da oferta.' : null,
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
                'net_efficiency_score' => $netEfficiencyScore,
                'pickup_burden_score' => $pickupBurdenScore,
                'destination_score' => $destinationContext['score'],
                'net_hourly_score' => $netHourlyScore,
            ],
            'driver_preferences' => $preferences,
            'net' => [
                'estimated_operating_cost' => $quotedFare > 0 ? $estimatedCost : null,
                'net_fare' => $netFare,
                'net_fare_per_km' => $netFarePerKm,
                'net_hourly_rate' => $netHourlyRate,
                'cost_estimated' => $costEstimated,
            ],
            'offer' => [
                'quoted_fare' => $quotedFare > 0 ? round($quotedFare, 2) : null,
                'pickup_distance_km' => $pickupDistanceKm,
                'trip_distance_km' => $tripDistanceKmProvided,
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
            'trip_distance_km' => $tripDistanceKmProvided,
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
            'estimated_operating_cost' => $result['net']['estimated_operating_cost'],
            'net_fare' => $netFare,
            'net_fare_per_km' => $netFarePerKm,
            'net_hourly_rate' => $netHourlyRate,
            'cost_estimated' => $costEstimated,
            'reasons' => $reasons,
            'raw_payload' => $payload['raw_payload'] ?? $payload,
            'evaluated_at' => now(),
        ]);

        return $result;
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

        $tripDistancesKm = $history
            ->map(fn (DriverTrip $trip): ?float => $trip->distance_miles ? ((float) $trip->distance_miles * 1.60934) : null)
            ->filter();

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
            'avg_trip_distance_km' => $tripDistancesKm->avg() ? round((float) $tripDistancesKm->avg(), 2) : 7.0,
        ];
    }

    private function netEfficiencyScore(float $quotedFare, ?float $netFarePerKm, float $avgNetFarePerKm): int
    {
        if ($quotedFare <= 0 || $netFarePerKm === null) {
            return 35;
        }

        $ratio = $netFarePerKm / max(0.5, $avgNetFarePerKm);

        return (int) max(5, min(100, round($ratio * 62)));
    }

    private function pickupBurdenScore(?float $pickupDistanceKm, ?int $pickupEtaMinutes): int
    {
        $distancePenalty = $pickupDistanceKm !== null ? ($pickupDistanceKm * 11) : 18;

        // ETA so penaliza o que exceder o esperado para aquela distancia (~2,2 min/km em area urbana),
        // senao distancia e ETA acabam penalizando duas vezes o mesmo deslocamento.
        $etaPenalty = 0.0;
        if ($pickupEtaMinutes !== null) {
            $expectedEtaMinutes = $pickupDistanceKm !== null ? ($pickupDistanceKm * 2.2) : 6.0;
            $etaPenalty = max(0.0, $pickupEtaMinutes - $expectedEtaMinutes) * 5;
        }

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

    private function netHourlyScore(?float $netHourlyRate, float $minHourlyRate): int
    {
        if ($netHourlyRate === null) {
            return 40;
        }

        $ratio = $netHourlyRate / max(1.0, $minHourlyRate);

        return (int) max(5, min(100, round($ratio * 60)));
    }

    private function finalRecommendation(
        int $decisionScore,
        int $pickupBurdenScore,
        string $destinationRisk,
        float $quotedFare,
        ?float $netFarePerKm,
        ?float $netHourlyRate,
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
            || ($netFarePerKm !== null && $netFarePerKm < $preferences['min_fare_per_km'])
            || ($netHourlyRate !== null && $netHourlyRate < $preferences['min_hourly_rate'])) {
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
        ?float $netFarePerKm,
        ?float $netHourlyRate,
        ?float $pickupDistanceKm,
        ?int $pickupEtaMinutes,
        array $preferences
    ): ?string {
        if ($quotedFare > 0 && $quotedFare < $preferences['min_offer_fare']) {
            return 'A oferta ficou abaixo do valor minimo que voce definiu para hoje.';
        }

        if ($netFarePerKm !== null && $netFarePerKm < $preferences['min_fare_per_km']) {
            return 'O ganho liquido por km, depois do combustivel, ficou abaixo da sua meta configurada.';
        }

        if ($netHourlyRate !== null && $netHourlyRate < $preferences['min_hourly_rate']) {
            return 'A projecao liquida por hora nao atingiu a sua meta operacional.';
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

    private function netFareReason(
        float $quotedFare,
        float $estimatedCost,
        ?float $netFare,
        ?float $netHourlyRate,
        bool $costEstimated
    ): ?string {
        if ($quotedFare <= 0 || $netFare === null) {
            return 'A notificacao ainda nao trouxe valor confiavel da corrida.';
        }

        $hourlyLabel = $netHourlyRate !== null
            ? ' (~R$ '.number_format($netHourlyRate, 2, ',', '.').'/h liquido)'
            : '';

        $estimateNote = $costEstimated
            ? ' Estimativa com base no seu historico, pois a notificacao nao trouxe todos os dados de distancia.'
            : '';

        return 'Depois do custo estimado de combustivel (R$ '.number_format($estimatedCost, 2, ',', '.').
            '), sobram R$ '.number_format($netFare, 2, ',', '.').' liquidos'.$hourlyLabel.'.'.$estimateNote;
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

    private function totalMinutes(float $tripDistanceKm, ?int $pickupEtaMinutes, float $returnLegKm): float
    {
        $tripMinutes = max(8.0, ($tripDistanceKm / 24) * 60);
        $pickupMinutes = $pickupEtaMinutes !== null ? max(3, $pickupEtaMinutes) : 6;
        $returnMinutes = $returnLegKm > 0 ? ($returnLegKm / 24) * 60 : 0.0;

        return $pickupMinutes + $tripMinutes + $returnMinutes;
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
