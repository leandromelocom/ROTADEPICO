<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('asaas');
            $table->string('provider_payment_id', 80)->unique();
            $table->string('provider_subscription_id', 80)->nullable()->index();
            $table->string('event', 60)->nullable();
            $table->string('status', 40)->nullable()->index();
            $table->string('billing_type', 40)->nullable();
            $table->unsignedInteger('value_cents')->nullable();
            $table->text('invoice_url')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_charges');
    }
};
