<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeTrendsRequest;
use App\Jobs\AnalyzeAgencyTrends;
use Illuminate\Http\RedirectResponse;

class TrendAnalysisController extends Controller
{
    public function __invoke(AnalyzeTrendsRequest $request): RedirectResponse
    {
        AnalyzeAgencyTrends::dispatch($request->integer('agency_id'))->onQueue('analytics');

        return back()->with('success', 'Trend analizi kuyruğa alındı.');
    }
}
