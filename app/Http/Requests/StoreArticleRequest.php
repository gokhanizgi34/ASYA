<?php

namespace App\Http\Requests;

use App\ArticleStatus;
use App\Models\Article;
use App\SourceTrustStatus;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Article::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id')))],
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'min:20'],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'source_trust_status' => ['required', Rule::enum(SourceTrustStatus::class)],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'failure_message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $status = ArticleStatus::tryFrom((string) $this->input('status'));
            $trustStatus = SourceTrustStatus::tryFrom((string) $this->input('source_trust_status'));
            $role = $this->user()?->role;

            if ($role === UserRole::Editor && ! in_array($status, [ArticleStatus::Draft, ArticleStatus::PendingApproval], true)) {
                $validator->errors()->add('status', 'Editör yalnızca taslak veya onay bekleyen içerik oluşturabilir.');
            }

            if ($role === UserRole::AgencyOwner && $status === ArticleStatus::Failed) {
                $validator->errors()->add('status', 'Hatalı durumu yalnızca sistem işlemleri tarafından kullanılabilir.');
            }

            if ($status === ArticleStatus::Published && $trustStatus !== SourceTrustStatus::Verified) {
                $validator->errors()->add('source_trust_status', 'Yayınlanacak haberin kaynağı doğrulanmalıdır.');
            }

            if ($status === ArticleStatus::Failed && blank($this->input('failure_message'))) {
                $validator->errors()->add('failure_message', 'Hatalı içerik için hata açıklaması gereklidir.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator()
            ? $this->input('agency_id')
            : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'title' => trim((string) $this->input('title')),
            'slug' => Str::slug((string) $this->input('title')),
            'summary' => filled($this->input('summary')) ? trim((string) $this->input('summary')) : null,
            'body' => trim((string) $this->input('body')),
            'source_trust_status' => $this->input('source_trust_status', SourceTrustStatus::Unverified->value),
            'source_name' => filled($this->input('source_name')) ? trim((string) $this->input('source_name')) : null,
            'source_url' => filled($this->input('source_url')) ? trim((string) $this->input('source_url')) : null,
        ]);
    }
}
