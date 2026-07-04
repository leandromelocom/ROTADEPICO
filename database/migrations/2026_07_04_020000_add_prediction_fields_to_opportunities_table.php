<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('route_profile', 50)->nullable()->after('trend');
            $table->unsignedTinyInteger('queue_pressure')->default(50)->after('route_profile');
            $table->json('preferred_vehicle_types')->nullable()->after('queue_pressure');
            $table->json('preferred_shifts')->nullable()->after('preferred_vehicle_types');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'route_profile',
                'queue_pressure',
                'preferred_vehicle_types',
                'preferred_shifts',
            ]);
        });
    }
};
