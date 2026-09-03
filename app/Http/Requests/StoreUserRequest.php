<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'Parola alanı zorunludur.',
            'password.string' => 'Parola geçerli bir metin olmalıdır.',
            'password.max' => 'Parola 255 karakterden uzun olamaz.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($role === UserRole::SystemAdministrator && filled($this->input('agency_id'))) {
                $validator->errors()->add('agency_id', 'Sistem yöneticisi bir ajansa bağlanamaz.');
            }

            if ($role !== null && $role !== UserRole::SystemAdministrator && blank($this->input('agency_id'))) {
                $validator->errors()->add('agency_id', 'Ajans sahibi ve editör için ajans seçilmelidir.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
            'agency_id' => filled($this->input('agency_id')) ? $this->integer('agency_id') : null,
        ];

        if ($this->user()?->isAgencyOwner()) {
            $data['role'] = UserRole::Editor->value;
            $data['agency_id'] = $this->user()->agency_id;
        }

        $this->merge($data);
    }
}
