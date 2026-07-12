<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LocalApiBenchmarkCommand extends Command
{
    protected $signature = 'benchmark:local-api
        {--base-url=http://127.0.0.1:8000 : Local server base URL}
        {--app-key=AK_DEMO_MHT-10001 : Demo API app key}
        {--app-secret=secret : Demo API app secret}
        {--target=/api/v1/products : Target path after token exchange}
        {--requests=50 : Number of requests to send}';

    protected $description = 'Run a lightweight local HTTP benchmark against the public API.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $requests = max(1, (int) $this->option('requests'));
        $path = '/'.ltrim((string) $this->option('target'), '/');

        try {
            $token = $this->issueToken($baseUrl);
        } catch (ConnectionException $exception) {
            $this->error("Unable to connect to {$baseUrl}. Start artisan serve or Octane first.");
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($token === null) {
            return self::FAILURE;
        }

        $durations = [];
        $failed = 0;

        $this->line("Benchmarking {$baseUrl}{$path}");

        for ($i = 0; $i < $requests; $i++) {
            $startedAt = hrtime(true);

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->get($baseUrl.$path);

                if (! $response->successful()) {
                    $failed++;
                }
            } catch (ConnectionException) {
                $failed++;
            }

            $durations[] = (hrtime(true) - $startedAt) / 1_000_000;
        }

        sort($durations);

        $totalMs = array_sum($durations);
        $avgMs = $totalMs / count($durations);
        $p95Index = min(count($durations) - 1, (int) ceil(count($durations) * 0.95) - 1);
        $p95Ms = $durations[$p95Index];
        $errorRate = $failed / $requests;

        $this->table(
            ['Metric', 'Value'],
            [
                ['base_url', $baseUrl],
                ['path', $path],
                ['requests', (string) $requests],
                ['avg_ms', number_format($avgMs, 2)],
                ['p95_ms', number_format($p95Ms, 2)],
                ['error_rate', number_format($errorRate * 100, 2).'%'],
            ],
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function issueToken(string $baseUrl): ?string
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->post($baseUrl.'/api/v1/auth/token', [
                'app_key' => $this->option('app-key'),
                'app_secret' => $this->option('app-secret'),
            ]);

        if (! $response->successful()) {
            $this->error('Unable to issue benchmark access token.');
            $this->line($response->body());

            return null;
        }

        $token = $response->json('data.access_token');

        if (! is_string($token) || $token === '') {
            $this->error('Token response did not contain data.access_token.');

            return null;
        }

        return $token;
    }
}
