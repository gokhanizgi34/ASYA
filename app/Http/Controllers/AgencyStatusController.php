<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAgencyStatusRequest;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;

class AgencyStatusController extends Controller
{
    public function __invoke(UpdateAgencyStatusRequest $request, Agency $agency): RedirectResponse
    {
        $agency->update(['is_active' => $request->validated('is_active')]);

        $message = $agency->is_active ? 'Ajans aktif edildi.' : 'Ajans pasif edildi.';

        return redirect()->route('agencies.index')->with('success', $message);
    }
}
