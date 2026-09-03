<?php

namespace App\Services;

use App\ErrorLogStatus;
use App\ErrorSeverity;
use App\Mail\ErrorAlertMail;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorLogRecorder
{
    public function __construct(private NotificationCenter $notifications, private AgencyMailSender $mailSender) {}

    public function record(Throwable $exception, ?Request $request = null, ?User $user = null): ?ErrorLog
    {
        try {
            if ($this->shouldIgnore($exception) || ! Schema::hasTable('error_logs')) {
                return null;
            }

            $request ??= app()->bound('request') ? request() : null;
            $resolvedUser = $user ?? $request?->user();
            $resolvedUser = $resolvedUser instanceof User ? $resolvedUser : null;
            $agencyId = $resolvedUser?->agency_id;
            $scopeKey = $agencyId === null ? 'system' : 'agency:'.$agencyId;
            $message = $this->sanitize($exception->getMessage());
            $file = $this->relativePath($exception->getFile());
            $fingerprint = hash('sha256', implode('|', [
                $exception::class,
                $this->normalizeForFingerprint($message),
                $file,
                (string) $exception->getLine(),
            ]));

            $errorLog = DB::transaction(function () use ($agencyId, $exception, $file, $fingerprint, $message, $request, $resolvedUser, $scopeKey): ErrorLog {
                $errorLog = ErrorLog::query()
                    ->where('scope_key', $scopeKey)
                    ->where('fingerprint', $fingerprint)
                    ->lockForUpdate()
                    ->first();

                $attributes = [
                    'agency_id' => $agencyId,
                    'user_id' => $resolvedUser?->getKey(),
                    'severity' => $this->severity($exception),
                    'status' => ErrorLogStatus::Open,
                    'exception_class' => $exception::class,
                    'message' => $message,
                    'file' => $file,
                    'line' => $exception->getLine(),
                    'http_method' => $request?->method(),
                    'path' => $request === null ? null : Str::limit($request->path(), 2048, ''),
                    'route_name' => $request?->route()?->getName(),
                    'context' => $this->context($exception, $request),
                    'last_seen_at' => now(),
                    'resolved_by_id' => null,
                    'resolved_at' => null,
                    'resolution_note' => null,
                ];

                if ($errorLog instanceof ErrorLog) {
                    $errorLog->fill($attributes);
                    $errorLog->occurrences++;
                    $errorLog->save();

                    return $errorLog->refresh();
                }

                return ErrorLog::query()->create($attributes + [
                    'scope_key' => $scopeKey,
                    'fingerprint' => $fingerprint,
                    'occurrences' => 1,
                    'first_seen_at' => now(),
                ]);
            }, 3);

            $this->notifications->errorRecorded($errorLog);

            if ($errorLog->occurrences === 1) {
                $this->mailSender->send($errorLog->agency_id, new ErrorAlertMail($errorLog));
            }

            return $errorLog;
        } catch (Throwable) {
            return null;
        }
    }

    private function shouldIgnore(Throwable $exception): bool
    {
        if ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException) {
            return true;
        }

        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500;
    }

    private function severity(Throwable $exception): ErrorSeverity
    {
        if ($exception instanceof \Error) {
            return ErrorSeverity::Critical;
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return ErrorSeverity::Warning;
        }

        return ErrorSeverity::Error;
    }

    /** @return array<string, mixed> */
    private function context(Throwable $exception, ?Request $request): array
    {
        return [
            'environment' => app()->environment(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 255, ''),
            'ip_hash' => $request === null ? null : hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'trace' => collect(array_slice($exception->getTrace(), 0, 12))->map(fn (array $frame): array => [
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
                'file' => isset($frame['file']) ? $this->relativePath((string) $frame['file']) : null,
                'line' => $frame['line'] ?? null,
            ])->all(),
        ];
    }

    private function sanitize(string $message): string
    {
        $sanitized = preg_replace([
            '/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i',
            '/(password|passwd|token|secret|api[_-]?key)\s*[:=]\s*([^\s,;]+)/i',
            '/([a-z][a-z0-9+.-]*:\/\/[^:\s]+:)[^@\s]+@/i',
        ], [
            'Bearer [MASKELENDİ]',
            '$1=[MASKELENDİ]',
            '$1[MASKELENDİ]@',
        ], $message) ?? 'Hata mesajı alınamadı.';

        return Str::limit($sanitized, 2000, '…');
    }

    private function normalizeForFingerprint(string $message): string
    {
        $normalized = preg_replace([
            '/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/i',
            '/\b\d+\b/',
        ], ['{uuid}', '{n}'], $message) ?? $message;

        return Str::lower($normalized);
    }

    private function relativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $basePath = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return Str::startsWith($normalizedPath, $basePath)
            ? Str::after($normalizedPath, $basePath)
            : basename($normalizedPath);
    }
}
