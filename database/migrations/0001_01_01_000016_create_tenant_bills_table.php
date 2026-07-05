<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户月度账单（租户域）。DDL 源：docs/database/schema-overview.md
 * 本期仅状态流转（A 方案），payment_* 为 C 预留字段。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 7);             // 2026-06
            $table->decimal('transaction_total', 14, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('api_usage_fee', 10, 2)->default(0);
            $table->decimal('api_overage_fee', 10, 2)->default(0);
            $table->decimal('total_receivable', 12, 2)->default(0);
            $table->decimal('merchant_reported_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->string('status')->default('pending_settlement');
            // ── C 字段预留 ──
            $table->string('payment_channel')->nullable();
            $table->string('external_transaction_no')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_meta')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_bills');
    }
};
