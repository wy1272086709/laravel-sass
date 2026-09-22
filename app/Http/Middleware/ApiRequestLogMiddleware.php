<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\LogApiRequestJob;
use App\Models\Api\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * 开放 API 请求日志 / 链路追踪中间件（挂在网关链最外层）。
 *
 * - 透传或生成 X-Request-Id（全链路追踪 ID），并回写到响应头；
 * - 请求结束（含被鉴权/限流/签名中间件拒绝的请求）投递 LogApiRequestJob
 *   异步落库 api_request_logs，不阻塞响应；
 * - 未捕获异常记 500 后原样抛出，交由框架渲染。
 *
 * Octane 注意：本中间件无共享状态（全部请求内局部变量），常驻 worker 安全。
 */
class ApiRequestLogMiddleware
{
    /** 探活等高频低价值路径不记日志（$request->path() 格式，无前导斜杠）。 */
    private const SKIP_PATHS = [
        'api/v1/ping',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        if (in_array($request->path(), self::SKIP_PATHS, true)) {
            return $next($request);
        }

        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);
        $startedAt = microtime(true);
        $requestedAt = now()->toDateTimeString();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, $requestId, $startedAt, $requestedAt, 500);

            throw $exception;
        }

        $response->headers->set('X-Request-Id', $requestId);
        $this->record($request, $requestId, $startedAt, $requestedAt, $response->getStatusCode());

        return $response;
    }

    /**
     * 调用方携带合法 X-Request-Id 则透传（跨系统串联链路），否则生成 UUID。
     */
    private function resolveRequestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $header) === 1) {
            return $header;
        }

        return (string) Str::uuid();
    }

    private function record(Request $request, string $requestId, float $startedAt, string $requestedAt, int $statusCode): void
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        LogApiRequestJob::dispatch([
            'tenant_id' => $apiKey?->tenant_id,
            'api_key_id' => $apiKey?->id,
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'endpoint' => $request->getPathInfo(),
            'status_code' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_address' => $request->ip(),
            'requested_at' => $requestedAt,
        ]);
    }
}
