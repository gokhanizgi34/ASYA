<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeAgencyTrends;
use App\Models\Agency;
use App\Models\TrendSnapshot;
use App\Models\TrendTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrendTopicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_sees_only_own_agency_trends(): void
    {
        $this->get(route('trends.index'))->assertRedirect(route('login'));
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $own = TrendTopic::factory()->for($agency)->create(['name' => 'Kendi Trendi']);
        $other = TrendTopic::factory()->for($otherAgency)->create(['name' => 'Başka Trend']);

        $this->actingAs($editor)->get(route('trends.index'))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
        $this->actingAs($editor)->get(route('trends.show', $other))->assertForbidden();
    }

    public function test_trend_detail_contains_snapshots_and_escaped_context(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $topic = TrendTopic::factory()->for($agency)->create(['context' => ['examples' => ['<script>alert(1)</script>'], 'sources' => ['Ajans']]]);
        TrendSnapshot::factory()->count(3)->for($topic)->sequence(
            ['period_end' => now()->subMinutes(30), 'period_start' => now()->subMinutes(45)],
            ['period_end' => now()->subMinutes(15), 'period_start' => now()->subMinutes(30)],
            ['period_end' => now(), 'period_start' => now()->subMinutes(15)],
        )->create();

        $this->actingAs($editor)->get(route('trends.show', $topic))->assertOk()->assertSee('15 dakikalık zaman serisi')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_owner_can_queue_only_own_agency_analysis_while_editor_cannot(): void
    {
        Queue::fake([AnalyzeAgencyTrends::class]);
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($owner)->post(route('trends.analyze'), ['agency_id' => $otherAgency->id])->assertRedirect();
        Queue::assertPushedOn('analytics', AnalyzeAgencyTrends::class, fn (AnalyzeAgencyTrends $job): bool => $job->agencyId === $agency->id);
        $this->actingAs($editor)->post(route('trends.analyze'), ['agency_id' => $agency->id])->assertForbidden();
    }

    public function test_system_administrator_can_queue_selected_agency_and_command_queues_active_agencies(): void
    {
        Queue::fake([AnalyzeAgencyTrends::class]);
        $administrator = User::factory()->systemAdministrator()->create();
        $activeAgency = Agency::factory()->create();
        Agency::factory()->create(['is_active' => false]);

        $this->actingAs($administrator)->post(route('trends.analyze'), ['agency_id' => $activeAgency->id])->assertRedirect();
        $this->artisan('trends:analyze')->assertSuccessful();
        Queue::assertPushed(AnalyzeAgencyTrends::class, 1);
    }
}
