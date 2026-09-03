<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
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
            $user = $this->route('user');
            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($user instanceof User && $this->user()?->is($user) && $role !== $user->role) {
                $validator->errors()->add('role', 'Kendi rolünüzü değiştiremezsiniz.');
            }

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
            'agency_id' => filled($this->input('agency_id')) ? $this->integer('agency_id') : null,
        ];

        if ($this->user()?->isAgencyOwner()) {
            $data['role'] = UserRole::Editor->value;
            $data['agency_id'] = $this->user()->agency_id;
        }

        $this->merge($data);
    }
}
