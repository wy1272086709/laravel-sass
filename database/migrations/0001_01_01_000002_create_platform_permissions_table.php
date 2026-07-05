<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 平台权限点（平台域）。平台后台 RBAC 权限目录（与开放 API 的 ApiPermission 不同）。
 * DDL 文档未给出，按惯例定义。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('group')->nullable(); // 复选树分组
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_permissions');
    }
};
