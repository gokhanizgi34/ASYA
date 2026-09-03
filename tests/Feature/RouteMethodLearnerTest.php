<?php

namespace Tests\Feature;

use App\HttpMethod;
use App\Models\Agency;
use App\Models\LearnedRoute;
use App\Models\PublishingTarget;
use App\Services\RouteMethodLearner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RouteMethodLearnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_strips_query_data_normalizes_dynamic_ids_and_groups_observations(): void
    {
        $agency = Agency::factory()->create();
        $learner = app(RouteMethodLearner::class);
        $first = $learner->observe(
            $agency->id,
            'https://News.Example.com/wp-json/wp/v2/posts/123?token=super-secret',
            HttpMethod::Get,
            200,
            'Yazı okuma',
        );
        $second = $learner->observe(
            $agency->id,
            'https://news.example.com/wp-json/wp/v2/posts/987?password=hidden',
            'GET',
            500,
            'Yazı okuma',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('learned_routes', 1);
        $route = LearnedRoute::query()->sole();
        $this->assertSame('news.example.com', $route->host);
        $this->assertSame('/wp-json/wp/v2/posts/{id}', $route->path_pattern);
        $this->assertStringNotContainsString('token', $route->path_pattern);
        $this->assertSame(1, $route->successful_count);
        $this->assertSame(1, $route->failed_count);
        $this->assertSame(50.0, $route->confidence);
        $this->assertSame(500, $route->last_status_code);
    }

    public function test_methods_and_tenants_are_learned_as_separate_routes(): void
    {
        $firstAgency = Agency::factory()->create();
        $secondAgency = Agency::factory()->create();
        $learner = app(RouteMethodLearner::class);
        $url = 'https://api.example.com/v1/articles';

        $learner->observe($firstAgency->id, $url, HttpMethod::Get, 200);
        $learner->observe($firstAgency->id, $url, HttpMethod::Post, 201);
        $learner->observe($secondAgency->id, $url, HttpMethod::Get, 200);

        $this->assertDatabaseCount('learned_routes', 3);
    }

    public function test_foreign_publishing_target_is_not_attached_to_observation(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $foreignTarget = PublishingTarget::factory()->for($otherAgency)->create();

        $route = app(RouteMethodLearner::class)->observe(
            $agency->id,
            'https://api.example.com/posts',
            HttpMethod::Post,
            201,
            publishingTargetId: $foreignTarget->id,
        );

        $this->assertNull($route->publishing_target_id);
    }

    public function test_invalid_url_and_method_are_rejected(): void
    {
        $agency = Agency::factory()->create();
        $learner = app(RouteMethodLearner::class);

        try {
            $learner->observe($agency->id, 'file:///etc/passwd', HttpMethod::Get, 200);
            $this->fail('Invalid URL should throw.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('learned_routes', 0);
        }

        $this->expectException(InvalidArgumentException::class);
        $learner->observe($agency->id, 'https://api.example.com', 'TRACE', 200);
    }
}
