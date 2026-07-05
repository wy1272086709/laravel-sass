<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品 SKU（租户域，本期预留，Filament 单 SKU 交互）。DDL 源：docs/database/schema-overview.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku_code');
            $table->json('specs');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'sku_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
    }
};
