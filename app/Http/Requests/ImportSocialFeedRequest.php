<?php

namespace App\Http\Requests;

use App\Models\SocialFeedSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportSocialFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('socialFeedSource');

        return $source instanceof SocialFeedSource && ($this->user()?->can('update', $source) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['payload' => ['required', 'string', 'json', 'max:500000']];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('payload')) {
                return;
            }

            $items = json_decode((string) $this->input('payload'), true);

            if (! is_array($items) || ! array_is_list($items)) {
                $validator->errors()->add('payload', 'Akış verisi bir JSON listesi olmalıdır.');

                return;
            }

            if (count($items) > 50) {
                $validator->errors()->add('payload', 'Tek aktarımda en fazla 50 kayıt alınabilir.');
            }
        }];
    }
}
