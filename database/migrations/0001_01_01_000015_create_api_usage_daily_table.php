<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API 日用量落库（租户域）。由 ApiUsageFlushJob 日终从 Redis 计数写入。
 * 文档仅给出 unique(tenant_id, usage_date)，其余按惯例。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('overage_count')->default(0);
            $table->unique(['tenant_id', 'usage_date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
    }
};
