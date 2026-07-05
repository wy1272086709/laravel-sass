<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiExceptionResponseMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            return $next($request);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 42201,
                'message' => 'Validation failed',
                'data' => $exception->errors(),
            ], 422);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return response()->json([
                'code' => 40401,
                'message' => 'Resource not found',
                'data' => null,
            ], 404);
        }
    }
}
