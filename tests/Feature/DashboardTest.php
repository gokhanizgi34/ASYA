<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Article;
use App\Models\NewsSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_operational_dashboard(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('KOMUTA MERKEZİ')
            ->assertSee('Sistem sağlığı')
            ->assertSee('Uyarı merkezi');
    }

    public function test_system_administrator_metrics_include_all_agencies_and_users(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $firstAgency = Agency::factory()->create();
        $secondAgency = Agency::factory()->create();
        User::factory()->count(2)->for($firstAgency)->create();
        User::factory()->for($secondAgency)->create();

        $response = $this->actingAs($administrator)->get(route('dashboard'));
        $metrics = $response->viewData('metrics');

        $response->assertOk();
        $this->assertSame(2, $metrics['summary']['agencies']);
        $this->assertSame(4, $metrics['summary']['users']);
    }

    public function test_agency_owner_metrics_are_scoped_to_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        User::factory()->count(2)->editor()->for($ownAgency)->create();
        User::factory()->count(3)->editor()->for($otherAgency)->create();

        $response = $this->actingAs($owner)->get(route('dashboard'));
        $metrics = $response->viewData('metrics');

        $response->assertOk()->assertSee($ownAgency->name)->assertDontSee($otherAgency->name);
        $this->assertSame(1, $metrics['summary']['agencies']);
        $this->assertSame(3, $metrics['summary']['users']);
    }

    public function test_dashboard_reads_article_and_source_metrics_when_modules_are_available(): void
    {
        $this->travelTo('2026-08-29 12:00:00');

        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();

        Article::factory()->for($ownAgency)->create(['created_at' => now()->subHour()]);
        Article::factory()->for($ownAgency)->create(['created_at' => now()->subHours(2)]);
        Article::factory()->for($otherAgency)->create(['created_at' => now()->subHour()]);
        NewsSource::factory()->for($ownAgency)->create();
        NewsSource::factory()->for($ownAgency)->create(['last_fetch_error' => 'Kaynak yanıt vermedi.']);
        NewsSource::factory()->for($otherAgency)->create();

        $firstResponse = $this->actingAs($owner)->get(route('dashboard'));
        $secondResponse = $this->actingAs($owner)->get(route('dashboard'));
        $metrics = $secondResponse->viewData('metrics');

        $firstResponse->assertOk();
        $secondResponse->assertOk()->assertSee('1 kaynak hata durumunda.');
        $this->assertSame(2, $metrics['summary']['articles_last_24_hours']);
        $this->assertSame(2, $metrics['summary']['total_sources']);
        $this->assertSame(2, $metrics['summary']['active_sources']);
        $this->assertSame(1, $metrics['summary']['failed_sources']);
        $this->assertSame(2, $metrics['article_chart'][6]['value']);
    }
}
