<?php

namespace Tests\Feature;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use App\PublishingProtocol;
use App\RemotePublicationStatus;
use App\Services\WordPressPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class WordPressPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_rest_driver_uploads_media_then_creates_sanitized_post(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('visuals/test.jpg', 'image-content');
        $publication = $this->publication(['content' => "## Güvenli başlık\n\nGüvenli metin <script>alert(1)</script>\n\nİkinci paragraf."]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([]);
            }
            if (str_ends_with($request->url(), '/media')) {
                return Http::response(['id' => 45], 201);
            }

            return Http::response(['id' => 91, 'link' => 'https://news.example.com/test-haberi'], 201);
        });

        $result = app(WordPressPublisher::class)->publish($publication);

        $this->assertSame('91', $result['post_id']);
        $this->assertSame(45, $result['media_id']);
        $this->assertSame(45, $publication->fresh()->remote_media_id);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/posts') && str_contains((string) data_get($request->data(), 'content'), '<h2>Güvenli başlık</h2>') && str_contains((string) data_get($request->data(), 'content'), '&lt;script&gt;') && ! str_contains((string) data_get($request->data(), 'content'), '<script>'));
        Http::assertSentCount(3);
        $this->assertDatabaseHas('learned_routes', ['agency_id' => $publication->agency_id, 'path_pattern' => '/wp-json/wp/v2/posts', 'method' => 'POST', 'successful_count' => 1]);
    }

    public function test_rest_driver_reuses_existing_slug_without_uploading_or_creating(): void
    {
        Storage::fake('public');
        $publication = $this->publication();
        $publication->forceFill(['remote_status' => RemotePublicationStatus::Publish])->save();
        Http::preventStrayRequests();
        Http::fake(['https://news.example.com/wp-json/wp/v2/posts*' => Http::response([['id' => 77, 'link' => 'https://news.example.com/existing']])]);

        $result = app(WordPressPublisher::class)->publish($publication);

        $this->assertSame('77', $result['post_id']);
        $this->assertTrue($result['response_meta']['reused_existing_post']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && data_get($request->data(), 'status') === 'publish'
            && data_get($request->data(), 'context') === 'view');
        Http::assertSentCount(1);
        $this->assertDatabaseHas('learned_routes', ['agency_id' => $publication->agency_id, 'path_pattern' => '/wp-json/wp/v2/posts', 'method' => 'GET', 'successful_count' => 1]);
    }

    public function test_xml_rpc_driver_uploads_media_and_creates_post(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('visuals/test.jpg', 'image-content');
        $publication = $this->publication(protocol: PublishingProtocol::WordPressXmlRpc);
        Http::preventStrayRequests();
        Http::fakeSequence('https://news.example.com/xmlrpc.php')
            ->push('<?xml version="1.0"?><methodResponse><params><param><value><struct><member><name>id</name><value><string>55</string></value></member></struct></value></param></params></methodResponse>')
            ->push('<?xml version="1.0"?><methodResponse><params><param><value><string>101</string></value></param></params></methodResponse>');

        $result = app(WordPressPublisher::class)->publish($publication);

        $this->assertSame('101', $result['post_id']);
        $this->assertSame(55, $result['media_id']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<methodName>wp.uploadFile</methodName>') && str_contains($request->body(), '<base64>'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<methodName>wp.newPost</methodName>'));
        $this->assertDatabaseHas('learned_routes', ['agency_id' => $publication->agency_id, 'path_pattern' => '/xmlrpc.php', 'method' => 'POST', 'successful_count' => 2]);
    }

    public function test_rest_driver_creates_missing_ai_taxonomy_terms_and_attaches_them(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('visuals/test.jpg', 'image-content');
        $publication = $this->publication([
            'taxonomy_names' => [
                'categories' => ['Yerel Haberler'],
                'tags' => ['İstanbul ulaşım'],
            ],
        ]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/posts')) {
                return Http::response([]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/categories')) {
                return Http::response([['id' => 14]]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/tags')) {
                return Http::response([]);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/tags')) {
                return Http::response(['id' => 24], 201);
            }
            if (str_ends_with($request->url(), '/media')) {
                return Http::response(['id' => 45], 201);
            }

            return Http::response(['id' => 91, 'link' => 'https://news.example.com/test-haberi'], 201);
        });

        app(WordPressPublisher::class)->publish($publication);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/posts')
            && data_get($request->data(), 'categories') === [2, 14]
            && data_get($request->data(), 'tags') === [8, 24]);
        Http::assertSentCount(6);
    }

    public function test_failed_rest_lookup_is_learned_with_its_actual_get_method(): void
    {
        Storage::fake('public');
        $publication = $this->publication();
        Http::preventStrayRequests();
        Http::fake(['https://news.example.com/wp-json/wp/v2/posts*' => Http::response(['message' => 'Unavailable'], 503)]);

        try {
            app(WordPressPublisher::class)->publish($publication);
            $this->fail('Request exception should be thrown.');
        } catch (RequestException) {
            $this->assertDatabaseHas('learned_routes', [
                'agency_id' => $publication->agency_id,
                'path_pattern' => '/wp-json/wp/v2/posts',
                'method' => 'GET',
                'successful_count' => 0,
                'failed_count' => 1,
                'last_status_code' => 503,
            ]);
        }
    }

    public function test_job_persists_success_and_failure_states(): void
    {
        $successful = $this->publication();
        $this->mock(WordPressPublisher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publish')->once()->andReturn(['post_id' => '123', 'media_id' => 45, 'url' => 'https://news.example.com/post', 'response_meta' => ['driver' => 'rest']]);
        });

        (new PublishArticleToWordPress($successful->id))->handle(app(WordPressPublisher::class));
        $this->assertSame(PublicationStatus::Published, $successful->fresh()->status);
        $this->assertSame('123', $successful->fresh()->remote_post_id);
        $this->assertSame(1, $successful->fresh()->attempt_count);

        $failed = $this->publication();
        $this->mock(WordPressPublisher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publish')->once()->andThrow(new RuntimeException('Uzak sunucu erişilemiyor.'));
        });

        (new PublishArticleToWordPress($failed->id))->handle(app(WordPressPublisher::class));
        $this->assertSame(PublicationStatus::Failed, $failed->fresh()->status);
        $this->assertSame('Uzak sunucu erişilemiyor.', $failed->fresh()->failure_message);
    }

    public function test_rest_driver_skips_terms_without_create_permission_and_still_publishes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('visuals/test.jpg', 'image-content');
        $publication = $this->publication([
            'taxonomy_names' => [
                'categories' => ['Yerel Haberler'],
                'tags' => ['İstanbul ulaşım'],
            ],
        ]);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([]);
            }
            if (str_contains($request->url(), '/categories') || str_contains($request->url(), '/tags')) {
                return Http::response(['code' => 'rest_cannot_create'], 401);
            }
            if (str_ends_with($request->url(), '/media')) {
                return Http::response(['id' => 45], 201);
            }

            return Http::response(['id' => 91, 'link' => 'https://news.example.com/test-haberi'], 201);
        });

        $result = app(WordPressPublisher::class)->publish($publication);

        $this->assertSame('91', $result['post_id']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/posts')
            && data_get($request->data(), 'categories') === [2]
            && data_get($request->data(), 'tags') === [8]);
        Http::assertSentCount(7);
    }

    /** @param array<string, mixed> $payloadOverrides */
    private function publication(array $payloadOverrides = [], PublishingProtocol $protocol = PublishingProtocol::WordPressRest): Publication
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['base_url' => 'https://news.example.com', 'protocol' => $protocol, 'username' => 'publisher', 'credential' => 'application-password']);
        $payload = array_replace_recursive([
            'title' => 'Test haberi', 'slug' => 'test-haberi', 'content' => 'Test içeriği', 'excerpt' => 'Test özeti', 'author' => 1,
            'categories' => [2], 'tags' => [8], 'meta' => ['asya_focus_keyword' => 'test'],
            'media' => ['disk' => 'public', 'path' => 'visuals/test.jpg', 'title' => 'Test görseli', 'alt_text' => 'Test görseli'],
        ], $payloadOverrides);

        return Publication::factory()->for($agency)->for($target, 'publishingTarget')->for($user, 'creator')->create(['payload' => $payload]);
    }
}
