<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 登录日志（平台域，记录双 Guard 登录尝试，含失败）。
 * 文档未给出 DDL，按 LoginResult 定义。无外键（可针对不存在用户）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->string('guard');                        // platform / merchant
            $table->string('identifier');                   // 登录账号（email）
            $table->string('result');                       // LoginResult enum
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_at');
            $table->index(['guard', 'logged_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
