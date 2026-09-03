<?php

namespace Tests\Feature;

use App\Jobs\AggregateAgencyAnalytics;
use App\Models\Agency;
use App\Models\AnalyticsSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_agency_user_sees_only_own_snapshots(): void
    {
        $this->get(route('analytics.index'))->assertRedirect(route('login'));
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        AnalyticsSnapshot::factory()->for($agency)->create(['report_date' => now()->toDateString(), 'raw_news_count' => 11]);
        AnalyticsSnapshot::factory()->for($otherAgency)->create(['report_date' => now()->toDateString(), 'raw_news_count' => 999]);

        $this->actingAs($editor)->get(route('analytics.index'))->assertOk()->assertSee('11')->assertDontSee('999')->assertSee($agency->name)->assertDontSee($otherAgency->name);
    }

    public function test_report_rejects_more_than_366_days(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->get(route('analytics.index', ['from' => now()->subDays(400)->toDateString(), 'to' => now()->toDateString()]))->assertSessionHasErrors('to');
    }

    public function test_owner_refreshes_only_own_agency_while_editor_is_forbidden(): void
    {
        Queue::fake([AggregateAgencyAnalytics::class]);
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($owner)->post(route('analytics.refresh'), ['agency_id' => $otherAgency->id])->assertRedirect();
        Queue::assertPushedOn('analytics', AggregateAgencyAnalytics::class, fn (AggregateAgencyAnalytics $job): bool => $job->agencyId === $agency->id);
        $this->actingAs($editor)->post(route('analytics.refresh'), ['agency_id' => $agency->id])->assertForbidden();
    }

    public function test_csv_export_is_tenant_scoped_utf8_and_formula_safe(): void
    {
        $agency = Agency::factory()->create(['name' => '=Tehlikeli Ajans']);
        $otherAgency = Agency::factory()->create(['name' => 'Başka Ajans']);
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        AnalyticsSnapshot::factory()->for($agency)->create(['report_date' => '2026-08-20']);
        AnalyticsSnapshot::factory()->for($otherAgency)->create(['report_date' => '2026-08-20']);

        $response = $this->actingAs($owner)->get(route('analytics.export', ['from' => '2026-08-01', 'to' => '2026-08-28']));

        $response->assertDownload('asya-analitik-2026-08-01-2026-08-28.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=Tehlikeli Ajans", $content);
        $this->assertStringNotContainsString('Başka Ajans', $content);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }
}
