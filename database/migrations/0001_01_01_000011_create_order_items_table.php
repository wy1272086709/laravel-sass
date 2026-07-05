<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 订单明细（租户域）。DDL 源：docs/database/schema-overview.md
 * product_id 默认 RESTRICT（保留历史，商品软删不级联）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('sku_id')->nullable()->constrained('product_skus');
            $table->string('product_name');
            $table->json('spec_snapshot');                   // 下单时规格快照
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
