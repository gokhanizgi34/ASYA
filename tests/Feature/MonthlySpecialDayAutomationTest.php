<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use App\Services\SpecialDayAiPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MonthlySpecialDayAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_automation_schedules_each_seo_question_for_the_first_upcoming_event(): void
    {
        $this->travelTo('2026-09-05 12:00:00');
        $agency = Agency::factory()->create();
        User::factory()->editor()->for($agency)->create();
        $events = [[
            'event_date' => '2026-10-29',
            'content_due_at' => '2026-10-01',
            'title' => 'Cumhuriyet Bayramı',
            'seo_topics' => ['29 Ekim neden Cumhuriyet Bayramı oldu?', '29 Ekim resmi tatil mi?'],
            'ai_provider' => 'Gemini',
        ]];

        $this->mock(SpecialDayAiPlanner::class, function (MockInterface $mock) use ($agency, $events): void {
            $mock->shouldReceive('plan')->once()->with($agency->id, 2026, 2)->andReturn($events);
        });
        $this->mock(GeneratedContentPublicationService::class, function (MockInterface $mock) use ($agency): void {
            $mock->shouldReceive('send')->twice()->withArgs(function (int $agencyId, User $user, array $content) use ($agency): bool {
                return $agencyId === $agency->id
                    && $content['source_type'] === 'special_day'
                    && filled($content['scheduled_for'])
                    && CarbonImmutable::parse($content['scheduled_for'])->isBefore('2026-10-29 00:00:00');
            });
        });

        $this->artisan('automation:monthly-special-days')->assertSuccessful();

        $event = EditorialCalendarEvent::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame('2026-10-29', $event->event_date->toDateString());
        $this->assertSame('Cumhuriyet Bayramı', $event->title);
        $this->assertSame('scheduled', $event->status);
    }
}
