<?php

namespace Tests\Feature;

use App\Jobs\PublishSocialPost;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use App\PublicationStatus;
use App\Services\AutomaticSocialPublisher;
use App\Services\SocialPublisher;
use App\SocialPostStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialAutoPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_adds_account_for_own_agency_and_token_is_encrypted(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('social-publishing.accounts.store'), $this->accountPayload($agency))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('social-publishing.accounts.store'), $this->accountPayload($otherAgency))
            ->assertRedirect();

        $account = SocialPublishingAccount::query()->sole();
        $this->assertSame($agency->id, $account->agency_id);
        $this->assertSame('cok-gizli-token', $account->access_token);
        $this->assertSame('x-api-key-value', $account->api_key);
        $this->assertSame('x-api-secret-value', $account->api_secret);
        $this->assertSame('x-token-secret-value', $account->access_token_secret);
        $this->assertSame('x_api_v2', $account->publish_mode);
        $rawAccount = DB::table('social_publishing_accounts')->first();
        $this->assertStringNotContainsString('cok-gizli-token', (string) $rawAccount->access_token);
        $this->assertStringNotContainsString('x-api-key-value', (string) $rawAccount->api_key);
        $this->assertStringNotContainsString('x-api-secret-value', (string) $rawAccount->api_secret);
        $this->assertStringNotContainsString('x-token-secret-value', (string) $rawAccount->access_token_secret);
    }

    public function test_editor_creates_only_for_own_account_and_platform_limit_is_enforced(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create(['platform' => 'x']);
        $foreignAccount = SocialPublishingAccount::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('social-publishing.posts.store'), $this->postPayload($foreignAccount))
            ->assertSessionHasErrors('social_publishing_account_id');

        $this->actingAs($editor)->post(route('social-publishing.posts.store'), $this->postPayload($account, [
            'content' => str_repeat('a', 281),
        ]))->assertSessionHasErrors('content');

        $this->actingAs($editor)->post(route('social-publishing.posts.store'), $this->postPayload($account))
            ->assertRedirect();

        $this->assertDatabaseHas('social_posts', ['agency_id' => $agency->id, 'status' => SocialPostStatus::Draft->value]);
    }

    public function test_dispatch_is_tenant_protected_and_job_publishes_in_local_sandbox(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create();
        $post = SocialPost::factory()->for($agency)->for($account, 'account')->create();

        $this->actingAs($otherEditor)->post(route('social-posts.dispatch', $post))->assertForbidden();
        $this->actingAs($editor)->post(route('social-posts.dispatch', $post))->assertRedirect();

        $this->assertSame(SocialPostStatus::Queued, $post->fresh()->status);
        Queue::assertPushed(PublishSocialPost::class, fn (PublishSocialPost $job): bool => $job->socialPostId === $post->id);

        (new PublishSocialPost($post->id))->handle(app(SocialPublisher::class));

        $post->refresh();
        $this->assertSame(SocialPostStatus::Published, $post->status);
        $this->assertStringStartsWith('local-', $post->external_id);
        $this->assertNotNull($post->published_at);
        $this->assertNotNull($account->fresh()->last_published_at);
    }

    public function test_scheduled_post_is_queued_with_delay_and_output_is_escaped(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create();
        $post = SocialPost::factory()->for($agency)->for($account, 'account')->create([
            'content' => '<script>alert(1)</script> planlı paylaşım',
            'scheduled_for' => now()->addHour(),
        ]);

        $this->actingAs($editor)->post(route('social-posts.dispatch', $post))->assertRedirect();
        Queue::assertPushed(PublishSocialPost::class, fn (PublishSocialPost $job): bool => $job->delay !== null);

        $this->actingAs($editor)->get(route('social-publishing.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_published_article_is_queued_once_for_x_with_municipality_mention(): void
    {
        Queue::fake([PublishSocialPost::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create([
            'account_handle' => '@asyahaber',
            'publish_mode' => 'x_api_v2',
            'mention_rules' => ['Pendik Belediyesi' => '@Pendik_Belediye'],
        ]);
        $article = Article::factory()->for($agency)->for($owner, 'author')->create([
            'title' => 'Pendik Belediyesi yeni kültür merkezini açtı',
            'summary' => 'Pendik Belediyesi yeni merkezi hizmete aldı.',
            'body' => 'Pendik Belediyesi tarafından tamamlanan merkez açıldı.',
            'source_name' => 'Pendik Belediyesi',
            'source_url' => 'https://www.pendik.bel.tr/tr/haber/merkez',
        ]);
        $target = PublishingTarget::factory()->for($agency)->create();
        Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->for($owner, 'creator')->create([
            'status' => PublicationStatus::Published,
            'remote_url' => 'https://news.example.com/pendik-kultur-merkezi',
            'published_at' => now(),
        ]);

        app(AutomaticSocialPublisher::class)->publish($article);
        app(AutomaticSocialPublisher::class)->publish($article);

        $post = SocialPost::query()->sole();
        $this->assertSame(SocialPostStatus::Queued, $post->status);
        $this->assertSame('https://news.example.com/pendik-kultur-merkezi', $post->link_url);
        $this->assertStringContainsString('@Pendik_Belediye', $post->content);
        Queue::assertPushed(PublishSocialPost::class, 1);
        $this->assertSame($account->id, $post->social_publishing_account_id);
    }

    public function test_live_x_publisher_sends_text_and_published_link(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => '2097999999999999999']], 201),
        ]);
        $agency = Agency::factory()->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create([
            'platform' => 'x',
            'publish_mode' => 'x_api_v2',
            'access_token' => 'user-access-token',
        ]);
        $post = SocialPost::factory()->for($agency)->for($account, 'account')->create([
            'content' => 'Yeni haber yayımlandı',
            'link_url' => 'https://news.example.com/yeni-haber',
            'status' => SocialPostStatus::Queued,
        ]);

        (new PublishSocialPost($post->id))->handle(app(SocialPublisher::class));

        $this->assertSame(SocialPostStatus::Published, $post->fresh()->status);
        $this->assertSame('2097999999999999999', $post->fresh()->external_id);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/tweets'
            && $request->hasHeader('Authorization', 'Bearer user-access-token')
            && data_get($request->data(), 'text') === "Yeni haber yayımlandı\n\nhttps://news.example.com/yeni-haber");
    }

    public function test_live_x_publisher_can_use_oauth1_user_context(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => '2097888888888888888']], 201),
        ]);
        $agency = Agency::factory()->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create([
            'platform' => 'x',
            'publish_mode' => 'x_api_v2',
            'access_token' => 'oauth-access-token',
            'api_key' => 'oauth-api-key',
            'api_secret' => 'oauth-api-secret',
            'access_token_secret' => 'oauth-token-secret',
        ]);
        $post = SocialPost::factory()->for($agency)->for($account, 'account')->create([
            'content' => 'Belediye haberi yayımlandı',
            'link_url' => 'https://news.example.com/belediye-haberi',
            'status' => SocialPostStatus::Queued,
        ]);

        (new PublishSocialPost($post->id))->handle(app(SocialPublisher::class));

        $this->assertSame(SocialPostStatus::Published, $post->fresh()->status);
        Http::assertSent(function (Request $request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return str_starts_with($authorization, 'OAuth ')
                && str_contains($authorization, 'oauth_signature=')
                && str_contains($authorization, 'oauth_consumer_key=');
        });
    }

    public function test_invalid_x_credentials_fail_social_post_without_reversing_other_publications(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/tweets' => Http::response(['detail' => 'Forbidden'], 403),
        ]);
        $agency = Agency::factory()->create();
        $account = SocialPublishingAccount::factory()->for($agency)->create([
            'platform' => 'x',
            'publish_mode' => 'x_api_v2',
            'access_token' => 'invalid-user-token',
        ]);
        $post = SocialPost::factory()->for($agency)->for($account, 'account')->create([
            'status' => SocialPostStatus::Queued,
        ]);

        (new PublishSocialPost($post->id))->handle(app(SocialPublisher::class));

        $this->assertSame(SocialPostStatus::Failed, $post->fresh()->status);
        $this->assertStringContainsString('tweet.write', (string) $post->fresh()->error_message);
    }

    /** @return array<string, mixed> */
    private function accountPayload(Agency $agency): array
    {
        return [
            'agency_id' => $agency->id,
            'name' => 'ASYA X Hesabı',
            'platform' => 'x',
            'account_handle' => '@asyahaber',
            'access_token' => 'cok-gizli-token',
            'api_key' => 'x-api-key-value',
            'api_secret' => 'x-api-secret-value',
            'access_token_secret' => 'x-token-secret-value',
            'is_active' => '1',
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function postPayload(SocialPublishingAccount $account, array $overrides = []): array
    {
        return array_merge([
            'social_publishing_account_id' => $account->id,
            'article_id' => null,
            'content' => 'ASYA Haber yeni içeriğini yayımladı.',
            'link_url' => 'https://example.com/haber',
            'media_url' => null,
            'scheduled_for' => null,
        ], $overrides);
    }
}
