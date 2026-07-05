<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 风控规则（平台域，无 tenant_id）。SDD §8 内置 5 条规则。
 * 文档未给出 DDL，按规则引擎定义。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // HIGH_FREQ_ORDER
            $table->string('name');
            $table->string('alert_type');                   // RiskAlertType enum
            $table->string('risk_level')->default('medium'); // RiskLevel enum
            $table->json('threshold_config')->nullable();   // 规则阈值参数
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_rules');
    }
};
