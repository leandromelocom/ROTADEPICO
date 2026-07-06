<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 120);
            $table->string('provider', 40)->default('uber');
            $table->string('platform', 30)->default('android');
            $table->string('device_label', 120)->nullable();
            $table->string('package_name', 160)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_notification_received_at')->nullable();
            $table->timestamp('last_decision_received_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
