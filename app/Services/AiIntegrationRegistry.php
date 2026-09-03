<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Database\Eloquent\Collection;

class AiIntegrationRegistry
{
    /** @return Collection<int, ApiIntegration> */
    public function forAgency(int $agencyId): Collection
    {
        return ApiIntegration::query()
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->whereIn('provider', collect(IntegrationProvider::cases())->filter->isAi()->pluck('value'))
            ->orderByRaw('CASE WHEN provider = ? THEN 0 ELSE 1 END', [IntegrationProvider::GoogleGemini->value])
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }
}
