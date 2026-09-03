<?php

namespace App\Services;

use App\ErrorSeverity;
use App\Models\ErrorLog;
use App\Models\Publication;
use App\Models\SystemNotification;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class NotificationCenter
{
    public function publicationFailed(Publication $publication): void
    {
        $publication->loadMissing(['article', 'publishingTarget']);
        $this->deliver(
            $this->recipients($publication->agency_id),
            [
                'agency_id' => $publication->agency_id,
                'type' => 'publication_failed',
                'severity' => ErrorSeverity::Error,
                'title' => 'Yayın işlemi başarısız',
                'message' => $publication->article->title.' → '.$publication->publishingTarget->name.': '.($publication->failure_message ?: 'Bilinmeyen hata'),
                'action_route' => 'publications.show',
                'action_parameters' => ['publication' => $publication->id],
                'fingerprint' => 'publication-failed:'.$publication->id,
            ],
        );
    }

    public function errorRecorded(ErrorLog $errorLog): void
    {
        if (! in_array($errorLog->severity, [ErrorSeverity::Error, ErrorSeverity::Critical], true)) {
            return;
        }

        $this->deliver(
            $this->recipients($errorLog->agency_id),
            [
                'agency_id' => $errorLog->agency_id,
                'type' => 'application_error',
                'severity' => $errorLog->severity,
                'title' => $errorLog->severity === ErrorSeverity::Critical ? 'Kritik sistem hatası' : 'Uygulama hatası',
                'message' => $errorLog->message,
                'action_route' => 'error-logs.show',
                'action_parameters' => ['errorLog' => $errorLog->id],
                'fingerprint' => 'error-log:'.$errorLog->id,
            ],
        );
    }

    /** @return Collection<int, User> */
    private function recipients(?int $agencyId): Collection
    {
        return User::query()->where('is_active', true)->where(function ($query) use ($agencyId): void {
            $query->where('role', UserRole::SystemAdministrator);

            if ($agencyId !== null) {
                $query->orWhere(fn ($query) => $query->where('role', UserRole::AgencyOwner)->where('agency_id', $agencyId));
            }
        })->get();
    }

    /** @param Collection<int, User> $recipients
     * @param  array{agency_id: int|null, type: string, severity: ErrorSeverity, title: string, message: string, action_route: string|null, action_parameters: array<string, mixed>|null, fingerprint: string}  $data
     */
    private function deliver(Collection $recipients, array $data): void
    {
        try {
            if (! Schema::hasTable('system_notifications')) {
                return;
            }

            DB::transaction(function () use ($recipients, $data): void {
                foreach ($recipients as $recipient) {
                    $notification = SystemNotification::query()->lockForUpdate()->firstOrNew([
                        'recipient_user_id' => $recipient->id,
                        'fingerprint' => hash('sha256', $data['fingerprint']),
                    ]);
                    $isExisting = $notification->exists;

                    $notification->fill([
                        'agency_id' => $data['agency_id'],
                        'type' => $data['type'],
                        'severity' => $data['severity'],
                        'title' => Str::limit(strip_tags($data['title']), 180, ''),
                        'message' => Str::limit(strip_tags($data['message']), 2000, '…'),
                        'action_route' => $data['action_route'],
                        'action_parameters' => $data['action_parameters'],
                        'occurrences' => $isExisting ? $notification->occurrences + 1 : 1,
                        'first_occurred_at' => $isExisting ? $notification->first_occurred_at : now(),
                        'last_occurred_at' => now(),
                        'read_at' => null,
                    ])->save();
                }
            }, 3);
        } catch (Throwable) {
            //
        }
    }
}
