<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对账差异单（租户域）。文档未给出 DDL，按 BillSettlementService 用例定义。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('difference_amount', 12, 2);
            $table->string('status')->default('unreconciled'); // ReconciliationStatus enum
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
    }
};
