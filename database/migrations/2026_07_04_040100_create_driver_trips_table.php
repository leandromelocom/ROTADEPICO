<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('external_trip_id');
            $table->string('status', 40)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('dropoff_at')->nullable();
            $table->decimal('fare', 10, 2)->nullable();
            $table->string('currency_code', 8)->nullable();
            $table->decimal('distance_miles', 8, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('surge_multiplier', 5, 2)->nullable();
            $table->string('start_city_name')->nullable();
            $table->decimal('start_city_latitude', 10, 6)->nullable();
            $table->decimal('start_city_longitude', 10, 6)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_trip_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trips');
    }
};
