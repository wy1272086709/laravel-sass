<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class TestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $insertId = DB::table('job_test')->insert([
            'content' => '插入内容的时间为：'. date('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        logger()->info('TestJob 执行完成，插入的 ID 为：'.$insertId);
    }
}
