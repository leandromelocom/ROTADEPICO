<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_offer_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('uber');
            $table->string('source')->default('notification');
            $table->string('external_offer_id')->nullable();
            $table->decimal('quoted_fare', 10, 2)->nullable();
            $table->string('currency_code', 8)->default('BRL');
            $table->decimal('pickup_distance_km', 8, 2)->nullable();
            $table->decimal('trip_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('pickup_eta_minutes')->nullable();
            $table->decimal('surge_multiplier', 4, 2)->nullable();
            $table->string('destination_zone_name')->nullable();
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
            $table->unsignedTinyInteger('decision_score');
            $table->string('recommendation', 40);
            $table->string('risk_level', 20);
            $table->string('destination_risk', 20);
            $table->string('matched_opportunity_zone')->nullable();
            $table->decimal('projected_hourly_rate', 10, 2)->nullable();
            $table->json('reasons')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['user_id', 'provider', 'evaluated_at']);
            $table->index(['recommendation', 'destination_risk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_offer_evaluations');
    }
};
