<?php

namespace Tests\Feature;

use App\Jobs\PublishSocialPost;
use App\Models\Agency;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use App\Services\SocialPublisher;
use App\SocialPostStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertStringNotContainsString('cok-gizli-token', (string) DB::table('social_publishing_accounts')->value('access_token'));
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

        (new PublishSocialPost($post->id))->handle(new SocialPublisher);

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

    /** @return array<string, mixed> */
    private function accountPayload(Agency $agency): array
    {
        return [
            'agency_id' => $agency->id,
            'name' => 'ASYA X Hesabı',
            'platform' => 'x',
            'account_handle' => '@asyahaber',
            'access_token' => 'cok-gizli-token',
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
