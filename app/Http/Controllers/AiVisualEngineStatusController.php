<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiVisualEngineStatusRequest;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;

class AiVisualEngineStatusController extends Controller
{
    public function __invoke(UpdateAiVisualEngineStatusRequest $request, SystemSettings $settings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agencyId = $request->validated('agency_id');
        $enabled = (bool) $request->validated('enabled');

        $settings->save(
            $agencyId,
            ['visual_ai_generation_enabled' => $enabled],
            [],
            $user,
        );

        return redirect()
            ->route('visual-assets.index', array_filter(['agency_id' => $agencyId]))
            ->with('success', $enabled
                ? 'AI görsel motoru açıldı. Kaynak görsel bulunamazsa AI görsel üretecek.'
                : 'AI görsel motoru kapatıldı. Yalnızca kaynak sitedeki görseller kullanılacak.');
    }
}
