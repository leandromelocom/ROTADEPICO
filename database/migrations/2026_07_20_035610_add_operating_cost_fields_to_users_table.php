<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('fuel_consumption_km_per_l', 6, 2)->nullable()->after('max_pickup_eta_minutes');
            $table->decimal('fuel_price_per_liter', 6, 2)->nullable()->after('fuel_consumption_km_per_l');
            $table->decimal('extra_cost_per_km', 6, 2)->nullable()->after('fuel_price_per_liter');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['fuel_consumption_km_per_l', 'fuel_price_per_liter', 'extra_cost_per_km']);
        });
    }
};
