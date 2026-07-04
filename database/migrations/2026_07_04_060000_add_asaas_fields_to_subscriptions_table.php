<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('currency');
            $table->string('provider_customer_id', 80)->nullable()->after('provider');
            $table->string('provider_subscription_id', 80)->nullable()->after('provider_customer_id');
            $table->string('provider_payment_link_id', 80)->nullable()->after('provider_subscription_id');
            $table->text('checkout_url')->nullable()->after('provider_payment_link_id');
            $table->string('last_payment_status', 40)->nullable()->after('checkout_url');
            $table->json('meta')->nullable()->after('last_payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'provider_customer_id',
                'provider_subscription_id',
                'provider_payment_link_id',
                'checkout_url',
                'last_payment_status',
                'meta',
            ]);
        });
    }
};
