<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class SystemRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_parameterless_authenticated_get_route_opens_without_server_error_for_all_roles(): void
    {
        $agency = Agency::factory()->create();
        $users = [
            User::factory()->systemAdministrator()->create(),
            User::factory()->agencyOwner()->for($agency)->create(),
            User::factory()->editor()->for($agency)->create(),
        ];
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn ($route): bool => ! str_contains($route->uri(), '{'))
            ->filter(fn ($route): bool => in_array('auth', $route->gatherMiddleware(), true)
                || collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_contains($middleware, 'EnsureUserIsActive')))
            ->values();

        $failures = [];

        foreach ($users as $user) {
            foreach ($routes as $route) {
                $response = $this->actingAs($user)->get('/'.$route->uri());

                if ($response->getStatusCode() >= 500) {
                    $failures[] = $user->role->value.' '.$route->methods()[0].' /'.$route->uri().' => '.$response->getStatusCode();

                    continue;
                }

                if (preg_match('/@(include|csrf|method)\b/', $response->getContent()) === 1) {
                    $failures[] = $user->role->value.' GET /'.$route->uri().' => ham Blade direktifi';
                }
            }
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }

    public function test_every_literal_named_route_referenced_by_blade_exists(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());
            preg_match_all("/route\(['\"]([^'\"]+)['\"]/", (string) $contents, $matches);

            foreach ($matches[1] as $routeName) {
                if (! Route::has($routeName)) {
                    $missing[] = $file->getPathname().' => '.$routeName;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), implode(PHP_EOL, $missing));
    }

    public function test_every_post_form_contains_csrf_protection(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/<form\b[^>]*method=["\']POST["\'][^>]*>(.*?)<\/form>/is', $contents, $matches);

            foreach ($matches[0] as $form) {
                if (! str_contains($form, '@csrf')) {
                    $missing[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), implode(PHP_EOL, $missing));
    }

    public function test_blade_directives_are_separated_so_they_can_all_be_compiled(): void
    {
        $invalid = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/@csrf@(include|method)|@method\([^\r\n]+?\)@include/', $contents) === 1) {
                $invalid[] = $file->getPathname();
            }
        }

        $this->assertSame([], $invalid, implode(PHP_EOL, $invalid));
    }

    public function test_every_application_route_is_behind_authentication_except_the_login_gate(): void
    {
        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route): bool => in_array($route->getName(), ['home', 'login', 'login.store'], true))
            ->reject(fn ($route): bool => $route->uri() === 'up')
            ->reject(fn ($route): bool => str_starts_with($route->uri(), '_boost/'))
            ->reject(fn ($route): bool => str_starts_with((string) $route->getName(), 'storage.'))
            ->filter(fn ($route): bool => ! in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn ($route): string => implode('|', $route->methods()).' /'.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, implode(PHP_EOL, $unprotected));
    }

    public function test_guest_is_redirected_from_every_parameterless_protected_screen(): void
    {
        $failures = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('GET', $route->methods(), true))
            ->filter(fn ($route): bool => ! str_contains($route->uri(), '{'))
            ->filter(fn ($route): bool => in_array('auth', $route->gatherMiddleware(), true))
            ->map(function ($route): ?string {
                $response = $this->get('/'.$route->uri());

                return $response->isRedirect(route('login')) ? null : '/'.$route->uri().' => '.$response->getStatusCode();
            })
            ->filter()
            ->values()
            ->all();

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }

    /** @return RegexIterator<RecursiveIteratorIterator<RecursiveDirectoryIterator>> */
    private function bladeFiles(): RegexIterator
    {
        return new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))),
            '/\.blade\.php$/i',
        );
    }
}
