<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_offer_evaluations', function (Blueprint $table): void {
            $table->decimal('estimated_operating_cost', 10, 2)->nullable()->after('projected_hourly_rate');
            $table->decimal('net_fare', 10, 2)->nullable()->after('estimated_operating_cost');
            $table->decimal('net_fare_per_km', 8, 2)->nullable()->after('net_fare');
            $table->decimal('net_hourly_rate', 10, 2)->nullable()->after('net_fare_per_km');
            $table->boolean('cost_estimated')->default(false)->after('net_hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('ride_offer_evaluations', function (Blueprint $table): void {
            $table->dropColumn([
                'estimated_operating_cost',
                'net_fare',
                'net_fare_per_km',
                'net_hourly_rate',
                'cost_estimated',
            ]);
        });
    }
};
