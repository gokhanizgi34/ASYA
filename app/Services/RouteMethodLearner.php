<?php

namespace App\Services;

use App\HttpMethod;
use App\Models\LearnedRoute;
use App\Models\PublishingTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RouteMethodLearner
{
    public function observe(
        int $agencyId,
        string $url,
        HttpMethod|string $method,
        ?int $statusCode,
        ?string $purpose = null,
        ?int $publishingTargetId = null,
    ): LearnedRoute {
        $httpMethod = $method instanceof HttpMethod ? $method : HttpMethod::tryFrom(Str::upper($method));

        if (! $httpMethod instanceof HttpMethod) {
            throw new InvalidArgumentException('Desteklenmeyen HTTP metodu.');
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT);

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Öğrenilecek rota geçerli bir HTTP adresi olmalıdır.');
        }

        if ($port !== null) {
            $host .= ':'.$port;
        }

        $pathPattern = $this->normalizePath((string) parse_url($url, PHP_URL_PATH));
        $isSuccessful = $statusCode !== null && $statusCode >= 200 && $statusCode < 400;
        $targetId = $publishingTargetId;

        if ($targetId !== null && ! PublishingTarget::query()->whereKey($targetId)->where('agency_id', $agencyId)->exists()) {
            $targetId = null;
        }

        return DB::transaction(function () use ($agencyId, $host, $httpMethod, $isSuccessful, $pathPattern, $purpose, $statusCode, $targetId): LearnedRoute {
            $route = LearnedRoute::query()
                ->where('agency_id', $agencyId)
                ->where('host', $host)
                ->where('path_pattern', $pathPattern)
                ->where('method', $httpMethod)
                ->lockForUpdate()
                ->first();

            if (! $route instanceof LearnedRoute) {
                $route = new LearnedRoute([
                    'agency_id' => $agencyId,
                    'publishing_target_id' => $targetId,
                    'host' => $host,
                    'path_pattern' => $pathPattern,
                    'method' => $httpMethod,
                    'successful_count' => 0,
                    'failed_count' => 0,
                    'is_enabled' => true,
                    'first_observed_at' => now(),
                ]);
            }

            $isSuccessful ? $route->successful_count++ : $route->failed_count++;
            $route->publishing_target_id = $targetId ?? $route->publishing_target_id;
            $route->purpose = filled($purpose) ? Str::limit(trim((string) $purpose), 255, '') : $route->purpose;
            $route->last_status_code = $statusCode;
            $route->last_observed_at = now();
            $route->last_success_at = $isSuccessful ? now() : $route->last_success_at;
            $observations = $route->successful_count + $route->failed_count;
            $route->confidence = $observations === 0 ? 0 : round(($route->successful_count / $observations) * 100, 2);
            $route->save();

            return $route->refresh();
        }, 3);
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/'.ltrim($path, '/');
        $normalized = preg_replace('/\/[0-9a-f]{8}-[0-9a-f-]{27,}(?=\/|$)/i', '/{uuid}', $normalized) ?? $normalized;
        $normalized = preg_replace('/\/(?:\d+|[0-9a-f]{20,})(?=\/|$)/i', '/{id}', $normalized) ?? $normalized;

        return Str::limit($normalized === '/' ? '/' : rtrim($normalized, '/'), 1000, '');
    }
}
