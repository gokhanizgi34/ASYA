<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Jobs\InspectPublishedUrlInSearchConsole;
use App\Jobs\PublishArticleToWordPress;
use App\Jobs\SubmitSitemapToSearchConsole;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use App\Services\GoogleSearchConsoleService;
use App\Services\WordPressPublisher;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleSearchConsoleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_configures_connection_tests_property_and_submits_news_sitemap(): void
    {
        Cache::clear();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->googleResponse($request));
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create([
            'name' => 'İlçe Haber',
            'base_url' => 'https://www.ilcehaber.com',
        ]);
        $credential = $this->serviceAccountJson();

        $this->actingAs($owner)
            ->get(route('api-integrations.create', ['provider' => IntegrationProvider::GoogleSearchConsole->value]))
            ->assertOk()
            ->assertSee('Yalnızca sitenizi seçin')
            ->assertSee('https://console.cloud.google.com/iam-admin/serviceaccounts/create', false)
            ->assertSee('https://console.cloud.google.com/iam-admin/serviceaccounts', false)
            ->assertSee('JSON dosyasını oluştur ve indir')
            ->assertSee('name="site_url"', false)
            ->assertSee('data-search-console-json', false)
            ->assertSee('İlçe Haber')
            ->assertDontSee('name="username"', false)
            ->assertDontSee('name="model"', false);

        $this->actingAs($owner)->post(route('api-integrations.store'), [
            'provider' => IntegrationProvider::GoogleSearchConsole->value,
            'site_url' => 'https://www.ilcehaber.com',
            'credential' => $credential,
        ])->assertRedirect(route('api-integrations.index'));

        $integration = ApiIntegration::query()->sole();
        $this->assertSame(IntegrationProvider::GoogleSearchConsole, $integration->provider);
        $this->assertSame(IntegrationAuthType::None, $integration->auth_type);
        $this->assertSame('sc-domain:ilcehaber.com', $integration->username);
        $this->assertSame('https://www.ilcehaber.com/news-sitemap.xml', $integration->model);
        $this->assertSame($credential, $integration->credential);
        $this->assertNotSame($credential, DB::table('api_integrations')->value('credential'));
        $integration->update(['last_error' => 'HTTP request returned status code 403: API has not been used.']);
        $this->actingAs($owner)->get(route('api-integrations.index'))
            ->assertOk()
            ->assertSee('Google izni henüz tamamlanmadı')
            ->assertSee('https://console.cloud.google.com/apis/library/searchconsole.googleapis.com?project=example-project', false)
            ->assertSee('asya-search-console@example-project.iam.gserviceaccount.com')
            ->assertDontSee('HTTP request returned status code 403');
        $integration->update(['last_error' => null]);

        $this->actingAs($owner)->post(route('api-integrations.test', $integration))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($owner)->post(route('api-integrations.search-console.sitemap', $integration))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($integration->fresh()->last_error);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && str_contains($request->url(), '/sitemaps/'));
    }

    public function test_invalid_service_account_and_foreign_sitemap_are_rejected(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('api-integrations.store'), [
            'provider' => IntegrationProvider::GoogleSearchConsole->value,
            'username' => 'sc-domain:example.com',
            'model' => 'https://foreign.example/news-sitemap.xml',
            'credential' => '{"type":"wrong"}',
        ])->assertSessionHasErrors(['model', 'credential']);

        $this->assertDatabaseCount('api_integrations', 0);
    }

    public function test_published_url_inspection_result_is_recorded_on_publication(): void
    {
        Cache::clear();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->googleResponse($request));
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->for($owner, 'author')->published()->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $publication = Publication::factory()
            ->for($agency)
            ->for($article)
            ->for($target, 'publishingTarget')
            ->for($owner, 'creator')
            ->create([
                'status' => PublicationStatus::Published,
                'remote_url' => 'https://example.com/haber/test-haberi',
            ]);
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'Google Search Console',
            'provider' => IntegrationProvider::GoogleSearchConsole,
            'model' => 'https://example.com/news-sitemap.xml',
            'base_url' => IntegrationProvider::GoogleSearchConsole->defaultBaseUrl(),
            'auth_type' => IntegrationAuthType::None,
            'username' => 'sc-domain:example.com',
            'credential' => $this->serviceAccountJson(),
            'is_active' => true,
        ]);

        (new InspectPublishedUrlInSearchConsole($publication->id))
            ->handle(app(GoogleSearchConsoleService::class));

        $publication->refresh();
        $this->assertSame('PASS', data_get($publication->response_meta, 'google_search_console.verdict'));
        $this->assertSame('Submitted and indexed', data_get($publication->response_meta, 'google_search_console.coverage_state'));
        $this->assertNull(data_get($publication->response_meta, 'google_search_console.error'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect');
    }

    public function test_successful_wordpress_publication_queues_delayed_index_inspection(): void
    {
        Queue::fake([InspectPublishedUrlInSearchConsole::class, SubmitSitemapToSearchConsole::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->for($owner, 'author')->published()->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $publication = Publication::factory()
            ->for($agency)
            ->for($article)
            ->for($target, 'publishingTarget')
            ->for($owner, 'creator')
            ->create(['status' => PublicationStatus::Queued]);
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'Google Search Console',
            'provider' => IntegrationProvider::GoogleSearchConsole,
            'model' => 'https://example.com/news-sitemap.xml',
            'base_url' => IntegrationProvider::GoogleSearchConsole->defaultBaseUrl(),
            'auth_type' => IntegrationAuthType::None,
            'username' => 'sc-domain:example.com',
            'credential' => $this->serviceAccountJson(),
            'is_active' => true,
        ]);
        $this->mock(WordPressPublisher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publish')->once()->andReturn([
                'post_id' => '501',
                'media_id' => null,
                'url' => 'https://example.com/haber/yeni-haber',
                'response_meta' => ['driver' => 'rest'],
            ]);
        });

        (new PublishArticleToWordPress($publication->id))->handle(app(WordPressPublisher::class));

        $this->assertSame(PublicationStatus::Published, $publication->fresh()->status);
        Queue::assertPushedOn('operations', SubmitSitemapToSearchConsole::class);
        Queue::assertPushedOn('operations', InspectPublishedUrlInSearchConsole::class, fn (InspectPublishedUrlInSearchConsole $job): bool => $job->publicationId === $publication->id);
    }

    private function googleResponse(Request $request): PromiseInterface
    {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response(['access_token' => 'google-access-token', 'expires_in' => 3600], 200);
        }

        if ($request->url() === 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect') {
            return Http::response([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Submitted and indexed',
                        'indexingState' => 'INDEXING_ALLOWED',
                        'pageFetchState' => 'SUCCESSFUL',
                        'robotsTxtState' => 'ALLOWED',
                        'lastCrawlTime' => '2026-09-06T00:31:00Z',
                    ],
                ],
            ], 200);
        }

        if ($request->method() === 'PUT' && str_contains($request->url(), '/sitemaps/')) {
            return Http::response([], 204);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), '/sites/')) {
            return Http::response(['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'], 200);
        }

        return Http::response(['error' => ['message' => 'Unexpected request']], 500);
    }

    private function serviceAccountJson(): string
    {
        $privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCwSgnmt9lEeXQF
Fl/2VZ0C4faEL+waaqHX/uDgi9OmzcFKtO/XdKiX0a1uprnvoBCd2p0cYyPQoeka
bBVICjcLLjvJ2NZ/SkbrSoTI94tKImgEmJjS/u7/Hx/StbbQ/hCnPp3kB4gGOu+L
YnJ3BA3o0LfR0XalunqZhxPLCi3EGV1qbiQ2Gy3jQWco+4OY2wLGr5px+tuiR9G8
b3dAqRiipwUq3uS91DTI4XpwbU8ZJJWV2d/ioAvLBXXvsn5aDArR00UU7Hs4UQfG
9zWcZ3+KiXWfzdCIkC2+bh1sVDDFgMevfBr6BR/BVoIzyZkk/fbdoG0dEVaUHOwC
JbzN9f8ZAgMBAAECggEABNMnQuLvQFounGEIuVON24wBqjaBjhYzCG1Xtu2SGl5G
lwaCtfE3GAigvYbfTzlLRcwQ7BD5OaGPdq3/RcckddrgaggplAxICp0x1dTolrEr
CvfTiZrOlw7sA7aozT7W3Szv4f4kG+o1r36UnBXxUi48NF2OPhTvfA+KsXjoLcC+
9aOWRRjV89JhCfb7i7IKSSFxGqSoYD5QTuJ1VQuJT6+dTBrMkzK6HFoODnWYpRuf
V/rXSHxHdfHhJ4EHqCVv+WbH/HHfUBk8Zu8Q5un6Hm5jTtPkAeK2ekIWr5woaCCB
Fb8EwTqrXlko0UU2/np4KzgpV9cWZUHBk3+iBismcQKBgQDoAb3NR12O+0G25WIb
ERoyU1tURBEYmTzUeyUJagMR4qY8PE8fqeqqix+XgDTr11jA9DeQyor/ACRqVB/9
xzUd6HAIOXgoq/AIQLpKfYkeKlhLBtGegqG3R6sGLbhvKi3Q4BTQNrqaC4Fw3NpH
2M8JbVqrbIUyYYXwppXUcx8CVQKBgQDChTQKJPX9ZB8nxTzNb0+vH68bxy+A99FM
J0DJuozzf4CSvJgkIfOIwVNcCbjrgYIez0eL2RurqQ1rIXgTROYJesN5SuUPzhi1
SI/RMfajiqbO/D7S26E+nYCW1esn/qAZpZIJsVeprJ9hNDNb/4WQYQyJ+rPhWnu+
zmC75pb1tQKBgQDbAQnRxRQj7DnUFCPPuQ0phFYp7TbWKZCqYrRLdq7/KxwQsD1g
flzuL/XaZUOPfPBi9CWfoBIlNFUrqc2pGWqimM9odBdhDSzAHZm8x1OwDfjamc01
+8n74MMoSfBXv1EQYvZCtebfkwzVJSVHvPlyxK9aMk5piHWO/TFiImmbIQKBgQCX
3ygiQ3lLvUAV7Qjr3Fx6fmJZbcrJBrOCoaMT3XLvKj1YU6b1jwx1WXucAHtAZH0T
UQKrTXctL3AqlJcLdF+mRxMXQEJXdLIV1/Fxg3DtfvN957OlLZVXLeGX4q0XLNYT
MBI1PyESeJR3cCopSfceIqeHkxWefObgsoEUM5TpgQKBgA0VZE8DxeL0HVVC1vBH
fInqrSPIkWNWWGemCt2t4UUVf3AWbkBp8ySkmi/cL4ItT+AqYP1ZV0jIG2UAwHi3
LjNWzpR2X2CJ782JbgH5MQlufNjnm5OLlyZkgVCPIClWUXEwugP2h0CpsMoGv/AT
fcwtzuqKV8uBQ4BduVzSRhq5
-----END PRIVATE KEY-----
PEM;

        return json_encode([
            'type' => 'service_account',
            'project_id' => 'example-project',
            'client_email' => 'asya-search-console@example-project.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ], JSON_THROW_ON_ERROR);
    }
}
