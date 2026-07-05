<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 套餐变更日志（平台域，审计，不随租户级联删除）。
 * 文档未给出 DDL，按 PackageChangeType 定义。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained(); // 审计：保留，不级联
            $table->foreignId('from_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('to_package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('change_type');                 // PackageChangeType enum
            $table->foreignId('operator_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_change_logs');
    }
};
