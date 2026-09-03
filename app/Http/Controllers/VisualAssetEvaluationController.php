<?php

namespace App\Http\Controllers;

use App\Models\VisualAsset;
use App\Services\VisualAssetEvaluator;
use App\VisualAssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class VisualAssetEvaluationController extends Controller
{
    public function __invoke(VisualAsset $visualAsset, VisualAssetEvaluator $evaluator): RedirectResponse
    {
        Gate::authorize('update', $visualAsset);
        $result = $evaluator->evaluate($visualAsset);

        if ($result['status'] !== VisualAssetStatus::Approved) {
            $result['is_selected'] = false;
        }

        $visualAsset->update($result);

        return back()->with('success', 'Görsel yeniden değerlendirildi.');
    }
}
