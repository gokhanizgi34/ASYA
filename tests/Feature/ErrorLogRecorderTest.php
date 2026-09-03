<?php

namespace Tests\Feature;

use App\ErrorLogStatus;
use App\Models\Agency;
use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorLogRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ErrorLogRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_repeated_errors_by_tenant_and_masks_secrets(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $request = Request::create('/entegrasyon?token=visible-in-query', 'POST');
        $request->setUserResolver(fn (): User => $owner);
        $exception = new RuntimeException('API failed password=super-secret Bearer abc.def.123');

        $first = app(ErrorLogRecorder::class)->record($exception, $request, $owner);
        $second = app(ErrorLogRecorder::class)->record($exception, $request, $owner);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertDatabaseCount('error_logs', 1);
        $errorLog = ErrorLog::query()->sole();
        $this->assertSame(2, $errorLog->occurrences);
        $this->assertSame($agency->id, $errorLog->agency_id);
        $this->assertSame('entegrasyon', $errorLog->path);
        $this->assertStringNotContainsString('super-secret', $errorLog->message);
        $this->assertStringNotContainsString('abc.def.123', $errorLog->message);
        $this->assertStringContainsString('[MASKELENDİ]', $errorLog->message);
    }

    public function test_same_error_is_separated_between_tenants_and_reopens_on_recurrence(): void
    {
        $firstAgency = Agency::factory()->create();
        $secondAgency = Agency::factory()->create();
        $firstOwner = User::factory()->agencyOwner()->for($firstAgency)->create();
        $secondOwner = User::factory()->agencyOwner()->for($secondAgency)->create();
        $exception = new RuntimeException('Service unavailable 503');

        $first = app(ErrorLogRecorder::class)->record($exception, user: $firstOwner);
        app(ErrorLogRecorder::class)->record($exception, user: $secondOwner);
        $first?->update([
            'status' => ErrorLogStatus::Resolved,
            'resolved_by_id' => $firstOwner->id,
            'resolved_at' => now(),
            'resolution_note' => 'Fixed',
        ]);
        app(ErrorLogRecorder::class)->record($exception, user: $firstOwner);

        $this->assertDatabaseCount('error_logs', 2);
        $this->assertSame(ErrorLogStatus::Open, $first?->refresh()->status);
        $this->assertSame(2, $first?->occurrences);
        $this->assertNull($first?->resolved_at);
    }

    public function test_validation_exceptions_are_not_persisted(): void
    {
        $result = app(ErrorLogRecorder::class)->record(
            ValidationException::withMessages(['title' => 'Required']),
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('error_logs', 0);
    }
}
