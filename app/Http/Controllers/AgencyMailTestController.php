<?php

namespace App\Http\Controllers;

use App\Mail\MailIntegrationTestMail;
use App\Models\AgencyMailSetting;
use App\Services\AgencyMailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AgencyMailTestController extends Controller
{
    public function __invoke(AgencyMailSetting $agencyMailSetting, AgencyMailSender $mailSender): RedirectResponse
    {
        Gate::authorize('update', $agencyMailSetting);
        $sent = $mailSender->send($agencyMailSetting->agency_id, new MailIntegrationTestMail($agencyMailSetting));
        $agencyMailSetting->forceFill(['last_tested_at' => now(), 'last_error' => $sent ? null : 'E-posta gönderilemedi. Sunucu bilgilerini kontrol edin.'])->save();

        return to_route('api-integrations.index')->with($sent ? 'success' : 'error', $sent ? 'Test e-postası gönderildi.' : 'Test e-postası gönderilemedi.');
    }
}
