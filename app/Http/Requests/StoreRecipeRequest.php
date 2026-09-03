<?php

namespace App\Http\Requests;

use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Recipe::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['category' => ['required', Rule::in(['main', 'cold', 'salad', 'dessert'])], 'title' => ['required', 'string', 'max:180'], 'ingredients' => ['required', 'string', 'min:10', 'max:5000'], 'instructions' => ['required', 'string', 'min:20', 'max:10000'], 'is_active' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['category' => trim((string) $this->input('category')), 'title' => trim((string) $this->input('title')), 'ingredients' => trim((string) $this->input('ingredients')), 'instructions' => trim((string) $this->input('instructions')), 'is_active' => $this->boolean('is_active')]);
    }
}
