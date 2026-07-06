<?php

namespace App\Support;

use Illuminate\Support\Str;

class UberNotificationParser
{
    public function parse(string $text): array
    {
        $normalized = Str::of($text)
            ->replace("\n", ' ')
            ->replace("\r", ' ')
            ->squish()
            ->toString();

        return array_filter([
            'quoted_fare' => $this->parseMoney($normalized),
            'pickup_eta_minutes' => $this->parsePickupEta($normalized),
            'pickup_distance_km' => $this->parsePickupDistance($normalized),
            'trip_distance_km' => $this->parseTripDistance($normalized),
            'surge_multiplier' => $this->parseSurgeMultiplier($normalized),
            'destination_zone_name' => $this->parseDestinationZone($normalized),
        ], fn (mixed $value): bool => $value !== null);
    }

    private function parseMoney(string $text): ?float
    {
        if (! preg_match('/R\\$\\s*([0-9]{1,3}(?:\\.[0-9]{3})*(?:,[0-9]{2})|[0-9]+(?:,[0-9]{2})?)/i', $text, $matches)) {
            return null;
        }

        return $this->normalizeDecimal($matches[1]);
    }

    private function parsePickupEta(string $text): ?int
    {
        if (preg_match('/(?:embarque|coleta|pickup)[^0-9]{0,18}([0-9]{1,3})\\s*min/i', $text, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/a\\s*([0-9]{1,3})\\s*min/i', $text, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parsePickupDistance(string $text): ?float
    {
        if (preg_match('/(?:embarque|coleta|pickup)[^0-9]{0,20}([0-9]+(?:[\\.,][0-9]+)?)\\s*km/i', $text, $matches)) {
            return $this->normalizeDecimal($matches[1]);
        }

        if (preg_match('/a\\s*([0-9]+(?:[\\.,][0-9]+)?)\\s*km/i', $text, $matches)) {
            return $this->normalizeDecimal($matches[1]);
        }

        return null;
    }

    private function parseTripDistance(string $text): ?float
    {
        if (preg_match('/(?:viagem|destino|trip)[^0-9]{0,24}([0-9]+(?:[\\.,][0-9]+)?)\\s*km/i', $text, $matches)) {
            return $this->normalizeDecimal($matches[1]);
        }

        return null;
    }

    private function parseSurgeMultiplier(string $text): ?float
    {
        if (preg_match('/([0-9]+(?:[\\.,][0-9]+)?)x/i', $text, $matches)) {
            return $this->normalizeDecimal($matches[1]);
        }

        return null;
    }

    private function parseDestinationZone(string $text): ?string
    {
        if (preg_match('/(?:destino|para|ate)\\s+([A-Za-zÀ-ÿ0-9\\-\\s]{4,60})$/u', $text, $matches)) {
            return trim($matches[1], " .,-");
        }

        if (preg_match('/(?:destino|para|ate)\\s+([A-Za-zÀ-ÿ0-9\\-\\s]{4,60})[\\.,]/u', $text, $matches)) {
            return trim($matches[1], " .,-");
        }

        return null;
    }

    private function normalizeDecimal(string $value): float
    {
        $normalized = str_replace('.', '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return round((float) $normalized, 2);
    }
}
