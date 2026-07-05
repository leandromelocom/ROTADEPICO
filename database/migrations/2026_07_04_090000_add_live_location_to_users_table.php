<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('last_known_latitude', 10, 7)->nullable()->after('location_permission_granted_at');
            $table->decimal('last_known_longitude', 10, 7)->nullable()->after('last_known_latitude');
            $table->timestamp('last_location_reported_at')->nullable()->after('last_known_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_known_latitude',
                'last_known_longitude',
                'last_location_reported_at',
            ]);
        });
    }
};
