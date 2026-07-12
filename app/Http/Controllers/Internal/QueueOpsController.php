<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Domain\Enums\QueueJobStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Redis\DeadLetterQueue;
use App\Infrastructure\Redis\DelayQueue;
use App\Models\System\QueueJobLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueOpsController extends Controller
{
    public function __construct(
        private readonly DelayQueue $delayQueue,
        private readonly DeadLetterQueue $deadLetterQueue,
    ) {}

    public function summary(): JsonResponse
    {
        $statusCounts = QueueJobLog::query()
            ->withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'jobs' => collect(QueueJobStatus::cases())
                    ->mapWithKeys(fn (QueueJobStatus $status): array => [$status->value => (int) ($statusCounts[$status->value] ?? 0)])
                    ->all(),
                'delayed' => [
                    'close_expired_orders' => $this->delayQueue->size('close_expired_orders'),
                ],
                'dead_letters' => [
                    'default' => $this->deadLetterQueue->size('default'),
                    'billing' => $this->deadLetterQueue->size('billing'),
                ],
            ],
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string'],
            'queue' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $jobs = QueueJobLog::query()
            ->withoutGlobalScopes()
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['queue'] ?? null, fn ($query, string $queue) => $query->where('queue', $queue))
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => collect($jobs->items())->map(fn (QueueJobLog $log): array => [
                'id' => $log->id,
                'job_uuid' => $log->job_uuid,
                'name' => $log->name,
                'queue' => $log->queue,
                'status' => $log->status->value,
                'tenant_id' => $log->tenant_id,
                'attempts' => $log->attempts,
                'payload' => $log->payload,
                'error' => $log->error,
                'queued_at' => $log->queued_at?->toJSON(),
                'started_at' => $log->started_at?->toJSON(),
                'finished_at' => $log->finished_at?->toJSON(),
            ])->all(),
            'meta' => [
                'page' => $jobs->currentPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'last_page' => $jobs->lastPage(),
            ],
        ]);
    }

    public function deadLetters(Request $request): JsonResponse
    {
        $queue = (string) $request->query('queue', 'default');

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $this->deadLetterQueue->list($queue),
        ]);
    }

    public function delayed(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'close_expired_orders' => $this->delayQueue->size('close_expired_orders'),
            ],
        ]);
    }

    public function panel(): JsonResponse
    {
        $statusCounts = QueueJobLog::query()
            ->withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'status_counts' => collect(QueueJobStatus::cases())
                    ->mapWithKeys(fn (QueueJobStatus $status): array => [$status->value => (int) ($statusCounts[$status->value] ?? 0)])
                    ->all(),
                'recent_jobs' => QueueJobLog::query()
                    ->withoutGlobalScopes()
                    ->latest('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (QueueJobLog $log): array => [
                        'id' => $log->id,
                        'name' => $log->name,
                        'queue' => $log->queue,
                        'status' => $log->status->value,
                        'tenant_id' => $log->tenant_id,
                        'finished_at' => $log->finished_at?->toJSON(),
                    ])
                    ->all(),
                'delayed' => [
                    'close_expired_orders' => $this->delayQueue->size('close_expired_orders'),
                ],
                'dead_letters' => [
                    'default' => $this->deadLetterQueue->size('default'),
                    'billing' => $this->deadLetterQueue->size('billing'),
                ],
            ],
        ]);
    }
}
