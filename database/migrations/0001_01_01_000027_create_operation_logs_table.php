<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 业务操作审计日志（租户域，通用表，订单模块为首期使用者）。
 * 后续计费/商品等模块复用本表，通过 (subject_type, action) 区分。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // 操作者：分列 FK（对齐 impersonation_logs）+ actor_kind + 冗余 label（对齐 login_logs.identifier）
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('merchant_user_id')->nullable()->constrained('merchant_users')->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('actor_kind');               // platform_user/merchant_user/api_key/system
            $table->string('actor_label');              // 冗余：防 FK 删除后失认

            // 操作主体：普通列，不用 morphs（项目无先例）；subject_type 存短名如 'order'
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            // 动作 + 状态流转
            $table->string('action');                   // OperationAction enum
            $table->string('from_status')->nullable();  // 业务状态值（如 OrderStatus value）
            $table->string('to_status')->nullable();

            // 详情 + 链路追踪
            $table->json('payload')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'api_key_id']);
            $table->index('merchant_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
