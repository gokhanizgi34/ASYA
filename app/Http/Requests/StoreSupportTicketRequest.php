<?php

namespace App\Http\Requests;

use App\SupportTicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['technical', 'integration', 'billing', 'suggestion', 'other'])],
            'priority' => ['required', Rule::enum(SupportTicketPriority::class)],
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
