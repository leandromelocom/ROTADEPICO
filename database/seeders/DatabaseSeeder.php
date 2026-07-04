<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'motorista@rotadepico.com.br'],
            [
                'name' => 'Motorista Demo',
                'phone' => '(11) 99999-0000',
                'city' => 'Sao Paulo',
                'vehicle_type' => 'Carro',
                'work_shift' => 'Noite',
                'password' => Hash::make('password'),
            ]
        );

        Opportunity::query()->delete();

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Itaim Bibi + Faria Lima',
            'score' => 96,
            'avg_fare' => 42.50,
            'surge_label' => 'Dinamica alta',
            'demand_level' => 'Muito alta',
            'best_start_at' => '18:20',
            'best_end_at' => '21:10',
            'active_driver_ratio' => 0.64,
            'latitude' => -23.5840110,
            'longitude' => -46.6746510,
            'pickup_hotspot' => 'Saidas de escritorios e restaurantes',
            'tip' => 'Posicione-se 2 quadras fora do eixo principal para embarques mais rapidos.',
            'trend' => 'subindo',
            'route_profile' => 'urbano-premium',
            'queue_pressure' => 34,
            'preferred_vehicle_types' => ['Carro', 'SUV'],
            'preferred_shifts' => ['Tarde', 'Noite'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Vila Olimpia + JK',
            'score' => 92,
            'avg_fare' => 38.90,
            'surge_label' => 'Executiva',
            'demand_level' => 'Alta',
            'best_start_at' => '17:40',
            'best_end_at' => '20:30',
            'active_driver_ratio' => 0.71,
            'latitude' => -23.5956310,
            'longitude' => -46.6857830,
            'pickup_hotspot' => 'Coworkings, bares e shoppings',
            'tip' => 'Boa regiao para corridas curtas que puxam sequencia.',
            'trend' => 'subindo',
            'route_profile' => 'giro-curto',
            'queue_pressure' => 42,
            'preferred_vehicle_types' => ['Carro', 'Moto'],
            'preferred_shifts' => ['Tarde', 'Noite'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Paulista + Consolacao',
            'score' => 88,
            'avg_fare' => 31.20,
            'surge_label' => 'Fluxo continuo',
            'demand_level' => 'Alta',
            'best_start_at' => '07:00',
            'best_end_at' => '09:30',
            'active_driver_ratio' => 0.58,
            'latitude' => -23.5564140,
            'longitude' => -46.6619770,
            'pickup_hotspot' => 'Hospitais, hoteis e metro',
            'tip' => 'Ideal para comecar cedo e fugir de ociosidade.',
            'trend' => 'estavel',
            'route_profile' => 'comutacao',
            'queue_pressure' => 28,
            'preferred_vehicle_types' => ['Carro', 'Moto'],
            'preferred_shifts' => ['Manha', 'Tarde'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Aeroporto de Congonhas',
            'score' => 84,
            'avg_fare' => 54.70,
            'surge_label' => 'Ticket premium',
            'demand_level' => 'Media/alta',
            'best_start_at' => '05:30',
            'best_end_at' => '08:00',
            'active_driver_ratio' => 0.82,
            'latitude' => -23.6271120,
            'longitude' => -46.6553650,
            'pickup_hotspot' => 'Terminal principal e hoteis proximos',
            'tip' => 'So vale quando a fila de motoristas estiver abaixo de 12 min.',
            'trend' => 'descendo',
            'route_profile' => 'ticket-longo',
            'queue_pressure' => 73,
            'preferred_vehicle_types' => ['Carro', 'SUV'],
            'preferred_shifts' => ['Manha', 'Tarde'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Pinheiros + Fradique',
            'score' => 86,
            'avg_fare' => 29.90,
            'surge_label' => 'Giro inteligente',
            'demand_level' => 'Alta',
            'best_start_at' => '19:10',
            'best_end_at' => '23:20',
            'active_driver_ratio' => 0.54,
            'latitude' => -23.5676620,
            'longitude' => -46.6922310,
            'pickup_hotspot' => 'Restaurantes, metro e bares',
            'tip' => 'Melhor para empilhar corridas medias sem ficar preso em fila.',
            'trend' => 'subindo',
            'route_profile' => 'giro-noturno',
            'queue_pressure' => 31,
            'preferred_vehicle_types' => ['Carro', 'Moto'],
            'preferred_shifts' => ['Noite', 'Madrugada'],
        ]);

        Opportunity::query()->create([
            'city' => 'Sao Paulo',
            'zone_name' => 'Barra Funda + Uninove',
            'score' => 78,
            'avg_fare' => 24.40,
            'surge_label' => 'Rotacao rapida',
            'demand_level' => 'Media/alta',
            'best_start_at' => '16:40',
            'best_end_at' => '19:00',
            'active_driver_ratio' => 0.47,
            'latitude' => -23.5254550,
            'longitude' => -46.6675090,
            'pickup_hotspot' => 'Terminal, faculdades e eventos',
            'tip' => 'Boa zona para moto ou carro leve que quer volume e pouca espera.',
            'trend' => 'estavel',
            'route_profile' => 'volume-rapido',
            'queue_pressure' => 26,
            'preferred_vehicle_types' => ['Moto', 'Carro'],
            'preferred_shifts' => ['Tarde', 'Noite'],
        ]);
    }
}
