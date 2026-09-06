<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class EditorialAutomationScheduleTest extends TestCase
{
    public function test_horoscopes_and_daily_recipes_are_scheduled_for_0001_in_istanbul(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach (['automation:midnight', 'app:generate-daily-menu'] as $command) {
            $event = $events->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

            $this->assertNotNull($event, $command.' zamanlayıcıda bulunamadı.');
            $this->assertSame('1 0 * * *', $event->expression);
            $this->assertSame('Europe/Istanbul', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
            $this->assertTrue($event->runInBackground);
        }
    }
}
