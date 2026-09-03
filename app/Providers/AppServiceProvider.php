<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->isPhpUnitProcess()) {
            $this->app->detectEnvironment(fn (): string => 'testing');

            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'queue.default' => 'sync',
                'session.driver' => 'array',
                'mail.default' => 'array',
            ]);
        }
    }

    public function boot(): void
    {
        Gate::define('viewAuthorizationMatrix', fn (User $user): bool => $user->isSystemAdministrator());
    }

    private function isPhpUnitProcess(): bool
    {
        return getenv('APP_ENV') === 'testing'
            || defined('PHPUNIT_COMPOSER_INSTALL')
            || str_contains((string) ($_SERVER['argv'][0] ?? ''), 'phpunit');
    }
}
