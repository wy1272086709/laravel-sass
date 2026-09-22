<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API 密钥 IP 黑白名单（租户域）。
 * - ip_whitelist：白名单非空时仅允许列表内 IP 调用；
 * - ip_blacklist：黑名单命中直接拒绝，优先级高于白名单；
 * - 条目支持精确 IP（203.0.113.5）、CIDR（203.0.113.0/24）、尾部通配（203.0.113.*）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->json('ip_whitelist')->nullable();  // string[]，null=未配置
            $table->json('ip_blacklist')->nullable();  // string[]，null=未配置
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['ip_whitelist', 'ip_blacklist']);
        });
    }
};
