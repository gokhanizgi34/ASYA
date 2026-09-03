<?php

namespace App\Http\Requests;

use App\ColumnistDraftStatus;
use App\Models\ColumnistDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateColumnistDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $d = $this->route('columnistDraft');

        return $d instanceof ColumnistDraft && ($this->user()?->can('update', $d) ?? false);
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return ['headline' => ['required', 'string', 'min:5', 'max:250'], 'body' => ['required', 'string', 'min:100', 'max:30000'], 'status' => ['required', Rule::enum(ColumnistDraftStatus::class)]];
    }

    /** @return array<int,callable(Validator):void> */
    public function after(): array
    {
        return [function (Validator $v): void {
            $s = ColumnistDraftStatus::tryFrom((string) $this->input('status'));
            if (! $this->user()?->isSystemAdministrator() && ! $this->user()?->isAgencyOwner() && in_array($s, [ColumnistDraftStatus::Approved, ColumnistDraftStatus::Rejected], true)) {
                $v->errors()->add('status', 'Editör yalnızca taslak veya inceleme durumunu seçebilir.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['headline' => trim((string) $this->input('headline')), 'body' => trim((string) $this->input('body'))]);
    }
}
