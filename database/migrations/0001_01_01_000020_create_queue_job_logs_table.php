<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 队列任务日志（租户域，队列中心面板数据源）。
 * 文档未给出 DDL，按 QueueJobStatus 状态机定义。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('job_uuid');
            $table->string('name');
            $table->string('queue')->default('default');
            $table->string('status')->default('pending');   // QueueJobStatus enum
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->index(['tenant_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_job_logs');
    }
};
