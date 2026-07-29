<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeploymentSeeder extends Seeder
{
    private const SEED_KEY = 'default-demo-data-v1';

    public function run(): void
    {
        if (DB::table('deployment_seed_runs')->where('key', self::SEED_KEY)->exists()) {
            $this->command?->info('Deployment seed already completed; skipping.');

            return;
        }

        DB::transaction(function (): void {
            $this->call(DatabaseSeeder::class);

            DB::table('deployment_seed_runs')->insert([
                'key' => self::SEED_KEY,
                'seeded_at' => now(),
            ]);
        });
    }
}
