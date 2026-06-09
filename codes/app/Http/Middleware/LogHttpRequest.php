<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogHttpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->requestId($request);
        $initialUserId = $request->user()?->getAuthIdentifier();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->writeLog(
                request: $request,
                requestId: $requestId,
                userId: $initialUserId,
                status: 500,
                startedAt: $startedAt,
                exception: $exception,
            );

            throw $exception;
        }

        $response->headers->set('X-Request-ID', $requestId);

        $this->writeLog(
            request: $request,
            requestId: $requestId,
            userId: $initialUserId ?? $request->user()?->getAuthIdentifier(),
            status: $response->getStatusCode(),
            startedAt: $startedAt,
            response: $response,
        );

        return $response;
    }

    private function writeLog(
        Request $request,
        string $requestId,
        int|string|null $userId,
        int $status,
        int $startedAt,
        ?Response $response = null,
        ?Throwable $exception = null,
    ): void {
        $context = [
            'event' => 'http.request',
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'route' => $request->route()?->getName(),
            'action' => $request->route()?->getActionName(),
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'content_type' => $request->getContentTypeFormat(),
            'request_bytes' => $this->contentLength($request->headers->get('content-length')),
            'response_bytes' => $this->contentLength($response?->headers->get('content-length')),
            'query_keys' => array_keys($request->query()),
        ];

        if ($exception) {
            $context['exception'] = $exception::class;
        }

        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };

        Log::channel('requests')->{$level}('HTTP request completed', $context);
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->headers->get('X-Request-ID');

        if (is_string($requestId) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $requestId)) {
            return $requestId;
        }

        return (string) Str::uuid();
    }

    private function contentLength(?string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
