<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('decision_profile', 20)->nullable()->after('work_shift');
            $table->decimal('min_offer_fare', 8, 2)->nullable()->after('decision_profile');
            $table->decimal('min_fare_per_km', 8, 2)->nullable()->after('min_offer_fare');
            $table->decimal('min_hourly_rate', 8, 2)->nullable()->after('min_fare_per_km');
            $table->decimal('max_pickup_distance_km', 8, 2)->nullable()->after('min_hourly_rate');
            $table->unsignedTinyInteger('max_pickup_eta_minutes')->nullable()->after('max_pickup_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'decision_profile',
                'min_offer_fare',
                'min_fare_per_km',
                'min_hourly_rate',
                'max_pickup_distance_km',
                'max_pickup_eta_minutes',
            ]);
        });
    }
};
