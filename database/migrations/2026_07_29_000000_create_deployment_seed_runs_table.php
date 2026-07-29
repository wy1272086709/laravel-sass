<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_seed_runs', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->timestamp('seeded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_seed_runs');
    }
};
