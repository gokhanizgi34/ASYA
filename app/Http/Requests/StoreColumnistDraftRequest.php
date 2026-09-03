<?php

namespace App\Http\Requests;

use App\Models\AiColumnist;
use App\Models\ColumnistDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreColumnistDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ColumnistDraft::class) ?? false;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return ['ai_columnist_id' => ['required', 'integer', Rule::exists('ai_columnists', 'id')->where('is_active', true)], 'topic' => ['required', 'string', 'min:5', 'max:250'], 'source_notes' => ['required', 'string', 'min:50', 'max:20000']];
    }

    /** @return array<int,callable(Validator):void> */
    public function after(): array
    {
        return [function (Validator $v): void {
            $c = AiColumnist::find($this->integer('ai_columnist_id'));
            if (! $c || (! $this->user()?->isSystemAdministrator() && $c->agency_id !== $this->user()?->agency_id)) {
                $v->errors()->add('ai_columnist_id', 'Köşe yazarı bu ajans için kullanılamaz.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['topic' => trim((string) $this->input('topic')), 'source_notes' => trim((string) $this->input('source_notes'))]);
    }
}
