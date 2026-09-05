<?php

namespace App\Http\Requests;

use App\Models\Publication;
use App\RemotePublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $publication = $this->route('publication');

        return $publication instanceof Publication && ($this->user()?->can('update', $publication) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'], 'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'min:20'], 'remote_status' => ['required', Rule::enum(RemotePublicationStatus::class)],
        ];
    }
}
