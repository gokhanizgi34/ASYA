<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\SpecialDayAiPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class EditorialCalendarGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_calendar_is_generated_for_own_agency_and_duplicate_run_updates_records(): void
    {
        $this->travelTo('2026-09-02 12:00:00');
        $agency = Agency::factory()->create();
        $user = User::factory()->editor()->for($agency)->create();
        $events = [[
            'event_date' => '2027-04-23',
            'content_due_at' => '2027-04-09',
            'title' => 'Ulusal Egemenlik ve Çocuk Bayramı',
            'seo_topics' => ['23 Nisan resmi tatil mi', '23 Nisan etkinlikleri'],
            'ai_provider' => 'Gemini',
        ]];
        $this->mock(SpecialDayAiPlanner::class, function (MockInterface $mock) use ($agency, $events): void {
            $mock->shouldReceive('plan')->twice()->with($agency->id, 2027, 1)->andReturn($events);
        });
        $payload = ['agency_id' => $agency->id, 'start_year' => 2027, 'years' => 1];
        $this->actingAs($user)->post(route('editorial-calendar.generate'), $payload)->assertRedirect(route('schedules.index'));
        $this->actingAs($user)->post(route('editorial-calendar.generate'), $payload)->assertRedirect(route('schedules.index'));
        $this->assertDatabaseCount('editorial_calendar_events', 1);
        $event = EditorialCalendarEvent::query()->firstOrFail();
        $this->assertSame('Ulusal Egemenlik ve Çocuk Bayramı', $event->title);
        $this->assertSame(['23 Nisan resmi tatil mi', '23 Nisan etkinlikleri'], $event->seo_topics);
    }

    public function test_agency_user_cannot_generate_calendar_for_another_agency(): void
    {
        $agency = Agency::factory()->create();
        $other = Agency::factory()->create();
        $user = User::factory()->editor()->for($agency)->create();
        $this->actingAs($user)->post(route('editorial-calendar.generate'), ['agency_id' => $other->id, 'start_year' => now()->year, 'years' => 1])->assertSessionHasErrors('agency_id');
        $this->assertDatabaseCount('editorial_calendar_events', 0);
    }

    public function test_invalid_ai_calendar_response_uses_official_local_fallback(): void
    {
        Http::fake(['https://93.184.216.34/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"events":[]}']]]])]);
        $agency = Agency::factory()->create();
        $integration = ApiIntegration::factory()->for($agency)->create(['provider' => IntegrationProvider::OpenAi, 'base_url' => 'https://93.184.216.34/v1/models', 'credential' => 'calendar-key']);

        $events = app(SpecialDayAiPlanner::class)->plan($agency->id, 2026, 1);

        $this->assertCount(7, $events);
        $this->assertSame('Yerel resmi takvim', $events[0]['ai_provider']);
        Http::assertSentCount(1);
    }
}
