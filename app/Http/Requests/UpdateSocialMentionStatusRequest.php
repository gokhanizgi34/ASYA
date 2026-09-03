<?php

namespace App\Http\Requests;

use App\Models\SocialMention;
use App\SocialMentionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialMentionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mention = $this->route('socialMention');

        return $mention instanceof SocialMention && ($this->user()?->can('update', $mention) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(SocialMentionStatus::class)]];
    }
}
