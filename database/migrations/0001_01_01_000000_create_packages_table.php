<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 套餐（平台域，无 tenant_id）。
 * DDL 源：docs/database/schema-overview.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('tier');                          // PackageTier enum
            $table->string('name');
            $table->decimal('price_monthly', 10, 2);
            $table->unsignedInteger('api_quota_daily');
            $table->unsignedInteger('merchant_limit')->default(1);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
