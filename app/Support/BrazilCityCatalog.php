<?php

namespace App\Support;

use Illuminate\Support\Str;

class BrazilCityCatalog
{
    private ?array $catalog = null;

    public function states(): array
    {
        return $this->catalog();
    }

    public function citiesForState(?string $state): array
    {
        $state = strtoupper(trim((string) $state));

        if ($state === '' || ! isset($this->catalog()[$state])) {
            return [];
        }

        return $this->catalog()[$state]['cities'];
    }

    public function allCities(): array
    {
        $cities = [];

        foreach ($this->catalog() as $state) {
            foreach ($state['cities'] as $city) {
                $cities[$city] = $city;
            }
        }

        return array_values($cities);
    }

    public function guessStateForCity(?string $city): ?string
    {
        $city = $this->normalize((string) $city);

        if ($city === '') {
            return null;
        }

        foreach ($this->catalog() as $uf => $state) {
            foreach ($state['cities'] as $candidate) {
                if ($this->normalize($candidate) === $city) {
                    return $uf;
                }
            }
        }

        return null;
    }

    public function findOfficialCity(?string $city): ?string
    {
        $city = $this->normalize((string) $city);

        if ($city === '') {
            return null;
        }

        foreach ($this->catalog() as $state) {
            foreach ($state['cities'] as $candidate) {
                if ($this->normalize($candidate) === $city) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = resource_path('data/brazil-cities.json');
        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->catalog = is_array($decoded) ? $decoded : [];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->squish()
            ->lower()
            ->ascii()
            ->toString();
    }
}
