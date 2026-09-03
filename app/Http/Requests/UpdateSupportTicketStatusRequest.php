<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use App\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('supportTicket');

        return $ticket instanceof SupportTicket && $this->user()?->can('update', $ticket) === true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(SupportTicketStatus::class)], 'admin_note' => ['nullable', 'string', 'max:3000']];
    }
}
