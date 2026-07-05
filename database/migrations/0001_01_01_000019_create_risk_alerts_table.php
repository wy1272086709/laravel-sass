<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 风控告警（租户域）。文档给出 index(status, risk_level)，其余按惯例。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');                         // RiskAlertType enum
            $table->string('risk_level')->default('medium'); // RiskLevel enum
            $table->string('status')->default('pending');   // RiskAlertStatus enum
            $table->json('context')->nullable();
            $table->timestamp('triggered_at');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('note')->nullable();
            $table->index(['status', 'risk_level']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_alerts');
    }
};
