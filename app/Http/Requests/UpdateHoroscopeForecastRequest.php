<?php

namespace App\Http\Requests;

use App\HoroscopeStatus;
use App\Models\HoroscopeForecast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHoroscopeForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        $forecast = $this->route('horoscopeForecast');

        return $forecast instanceof HoroscopeForecast && ($this->user()?->can('update', $forecast) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(HoroscopeStatus::class)],
            'general' => ['required', 'string', 'min:50', 'max:3000'],
            'love' => ['required', 'string', 'min:20', 'max:1500'],
            'career' => ['required', 'string', 'min:20', 'max:1500'],
            'money' => ['required', 'string', 'min:20', 'max:1500'],
            'health' => ['required', 'string', 'min:20', 'max:1500'],
            'lucky_color' => ['required', 'string', 'max:50'],
            'lucky_number' => ['required', 'integer', 'between:1,99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['general', 'love', 'career', 'money', 'health', 'lucky_color'] as $field) {
            $this->merge([$field => trim((string) $this->input($field))]);
        }
    }
}
