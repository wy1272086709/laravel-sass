<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品 SPU（租户域）。DDL 源：docs/database/schema-overview.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('product_code');                  // G-10002
            $table->string('name');
            $table->string('cover_image')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->json('specs')->nullable();               // {"颜色":"象牙白","尺码":"L"}
            $table->string('status')->default('listed');     // ProductStatus enum
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'product_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
