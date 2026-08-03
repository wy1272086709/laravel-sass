<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained();
            $table->string('provider', 32);
            $table->string('external_customer_id')->nullable();
            $table->string('external_subscription_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_subscription_id']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('package_id')->constrained();
            $table->string('order_no', 64)->unique();
            $table->string('provider', 32);
            $table->string('external_payment_id')->nullable();
            $table->string('idempotency_key', 128);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['provider', 'external_payment_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->string('event_type', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('tenant_subscriptions');
    }
};
