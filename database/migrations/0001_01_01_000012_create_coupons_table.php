<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 优惠券（租户域，营销）。
 * DDL 文档未给出，按惯例定义：
 *  - type=full_reduction 时 discount_value=满减金额（元），min_amount=门槛
 *  - type=discount 时 discount_value=折扣百分比（0-100，如 85 表示 8.5 折）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');                          // CouponType enum
            $table->string('status')->default('not_started'); // CouponStatus enum
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_amount', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
