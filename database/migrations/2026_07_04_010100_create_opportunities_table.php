<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('zone_name');
            $table->unsignedTinyInteger('score');
            $table->decimal('avg_fare', 8, 2);
            $table->string('surge_label', 50);
            $table->string('demand_level', 30);
            $table->string('best_start_at', 5);
            $table->string('best_end_at', 5);
            $table->decimal('active_driver_ratio', 4, 2);
            $table->string('pickup_hotspot');
            $table->string('tip');
            $table->string('trend', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
