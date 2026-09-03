<?php

namespace Tests\Feature;

use App\Mail\ErrorAlertMail;
use App\Models\Agency;
use App\Models\AgencyMailSetting;
use App\Models\User;
use App\Services\ErrorLogRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ErrorAlertMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_occurrence_sends_one_safe_error_alert(): void
    {
        Mail::fake();
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        AgencyMailSetting::factory()->for($agency)->create(['notification_email' => 'errors@example.com']);
        $exception = new RuntimeException('Sensitive API failure token=do-not-email');
        app(ErrorLogRecorder::class)->record($exception, user: $owner);
        app(ErrorLogRecorder::class)->record($exception, user: $owner);
        Mail::assertSent(ErrorAlertMail::class, 1);
        Mail::assertSent(ErrorAlertMail::class, fn ($mail) => $mail->hasTo('errors@example.com'));
    }
}
