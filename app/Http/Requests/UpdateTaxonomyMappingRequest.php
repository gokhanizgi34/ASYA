<?php

namespace App\Http\Requests;

use App\Models\TaxonomyMapping;

class UpdateTaxonomyMappingRequest extends StoreTaxonomyMappingRequest
{
    public function authorize(): bool
    {
        $mapping = $this->route('taxonomyMapping');

        return $mapping instanceof TaxonomyMapping && ($this->user()?->can('update', $mapping) ?? false);
    }

    protected function mappingForUniqueRule(): ?TaxonomyMapping
    {
        $mapping = $this->route('taxonomyMapping');

        return $mapping instanceof TaxonomyMapping ? $mapping : null;
    }
}
