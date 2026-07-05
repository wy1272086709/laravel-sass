<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 租户（实体本身，不使用 BelongsToTenant；平台可查全量）。
 * DDL 源：docs/database/schema-overview.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_code')->unique();         // MHT-89201
            $table->string('name');
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->foreignId('package_id')->constrained();
            $table->string('status')->default('enabled');    // TenantStatus enum
            $table->decimal('commission_rate', 5, 4)->default(0.0200);
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
