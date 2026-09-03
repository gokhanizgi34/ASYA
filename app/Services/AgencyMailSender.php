<?php

namespace App\Services;

use App\Models\AgencyMailSetting;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AgencyMailSender
{
    public function send(?int $agencyId, Mailable $mailable): bool
    {
        try {
            $setting = AgencyMailSetting::query()->where('is_active', true)
                ->where(function ($query) use ($agencyId): void {
                    if ($agencyId !== null) {
                        $query->where('agency_id', $agencyId);
                    }
                    $query->orWhereNull('agency_id');
                })->orderByRaw('agency_id is null')->first();
            $recipient = $setting?->notification_email ?? User::query()->where('is_active', true)->where('role', 'system_administrator')->value('email');
            if ($recipient === null) {
                return false;
            }
            if ($setting === null) {
                Mail::to($recipient)->send($mailable);

                return true;
            }
            $mailerName = 'agency_runtime_'.$setting->getKey();
            Config::set('mail.mailers.'.$mailerName, $setting->mailerConfig());
            $mailable->from($setting->from_address, $setting->from_name);
            if (Mail::getFacadeRoot() instanceof MailManager) {
                Mail::purge($mailerName);
            }
            Mail::mailer($mailerName)->to($recipient)->send($mailable);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
