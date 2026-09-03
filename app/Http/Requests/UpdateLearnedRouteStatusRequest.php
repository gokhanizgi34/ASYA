<?php

namespace App\Http\Requests;

use App\Models\LearnedRoute;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLearnedRouteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learnedRoute = $this->route('learnedRoute');

        return $learnedRoute instanceof LearnedRoute && ($this->user()?->can('update', $learnedRoute) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['is_enabled' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
    }
}
