<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_api_token_hash')->nullable()->after('remember_token');
            $table->timestamp('mobile_api_token_created_at')->nullable()->after('mobile_api_token_hash');
            $table->timestamp('mobile_api_token_last_used_at')->nullable()->after('mobile_api_token_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_api_token_hash',
                'mobile_api_token_created_at',
                'mobile_api_token_last_used_at',
            ]);
        });
    }
};
