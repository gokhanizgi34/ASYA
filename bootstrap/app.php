<?php

use App\Services\ErrorLogRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$isPhpUnitProcess = getenv('APP_ENV') === 'testing'
    || defined('PHPUNIT_COMPOSER_INSTALL')
    || str_contains((string) ($_SERVER['argv'][0] ?? ''), 'phpunit');

if ($isPhpUnitProcess) {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';
}

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->report(function (Throwable $exception): void {
            app(ErrorLogRecorder::class)->record($exception);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if ($isPhpUnitProcess) {
    $application->detectEnvironment(fn (): string => 'testing');
}

return $application;
