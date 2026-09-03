<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\AnalyticsSnapshot;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\BlacklistRule;
use App\Models\Campaign;
use App\Models\ContentBatch;
use App\Models\DatabaseBackup;
use App\Models\ErrorLog;
use App\Models\LearnedRoute;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\ScheduleEntry;
use App\Models\SystemSetting;
use App\Models\TaxonomyMapping;
use App\Models\TrendTopic;
use App\Models\User;
use App\Models\VisualAsset;
use App\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

class AuthorizationMatrix
{
    /** @return array<int, array{key: string, label: string, permissions: array<string, array<string, bool>>}> */
    public function rows(): array
    {
        return collect($this->resources())->map(function (array $resource): array {
            $permissions = [];

            foreach (UserRole::cases() as $role) {
                $user = $this->representativeUser($role);

                foreach ($resource['abilities'] as $ability => $label) {
                    $permissions[$role->value][$ability] = $this->allows(
                        $user,
                        $ability,
                        $resource['model'],
                        in_array($ability, ['viewAny', 'create', 'updateAny'], true),
                    );
                }
            }

            return [
                'key' => $resource['key'],
                'label' => $resource['label'],
                'permissions' => $permissions,
            ];
        })->all();
    }

    /** @return array<string, string> */
    public function abilityLabels(): array
    {
        return [
            'viewAny' => 'Görüntüle',
            'create' => 'Oluştur',
            'update' => 'Düzenle',
            'delete' => 'Sil',
        ];
    }

    /** @return array<int, array{key: string, label: string, model: class-string<Model>, abilities: array<string, string>}> */
    private function resources(): array
    {
        $standard = $this->abilityLabels();

        return [
            ['key' => 'agencies', 'label' => 'Ajanslar', 'model' => Agency::class, 'abilities' => $standard],
            ['key' => 'users', 'label' => 'Kullanıcılar', 'model' => User::class, 'abilities' => $standard],
            ['key' => 'raw-news', 'label' => 'Ham Haber Havuzu', 'model' => RawNewsItem::class, 'abilities' => $standard],
            ['key' => 'articles', 'label' => 'Haberler', 'model' => Article::class, 'abilities' => $standard],
            ['key' => 'prompts', 'label' => 'AI Promptları', 'model' => AiPrompt::class, 'abilities' => $standard],
            ['key' => 'visual-assets', 'label' => 'Görsel Motoru', 'model' => VisualAsset::class, 'abilities' => $standard],
            ['key' => 'content-batches', 'label' => 'İçerik Fabrikası', 'model' => ContentBatch::class, 'abilities' => $standard],
            ['key' => 'publications', 'label' => 'Yayın Merkezi', 'model' => Publication::class, 'abilities' => $standard],
            ['key' => 'publishing-targets', 'label' => 'Yayın Hedefleri', 'model' => PublishingTarget::class, 'abilities' => $standard],
            ['key' => 'trends', 'label' => 'Trend Motoru', 'model' => TrendTopic::class, 'abilities' => $standard],
            ['key' => 'campaigns', 'label' => 'Kampanyalar', 'model' => Campaign::class, 'abilities' => $standard],
            ['key' => 'schedules', 'label' => 'Planlama', 'model' => ScheduleEntry::class, 'abilities' => $standard],
            ['key' => 'analytics', 'label' => 'Analitik', 'model' => AnalyticsSnapshot::class, 'abilities' => $standard],
            ['key' => 'errors', 'label' => 'Hata Kayıtları', 'model' => ErrorLog::class, 'abilities' => $standard],
            ['key' => 'settings', 'label' => 'Sistem Ayarları', 'model' => SystemSetting::class, 'abilities' => ['viewAny' => 'Görüntüle', 'updateAny' => 'Düzenle']],
            ['key' => 'learned-routes', 'label' => 'Rota Öğrenici', 'model' => LearnedRoute::class, 'abilities' => $standard],
            ['key' => 'integrations', 'label' => 'Entegrasyonlar', 'model' => ApiIntegration::class, 'abilities' => $standard],
            ['key' => 'taxonomy', 'label' => 'Taksonomi', 'model' => TaxonomyMapping::class, 'abilities' => $standard],
            ['key' => 'blacklist', 'label' => 'Kara Liste', 'model' => BlacklistRule::class, 'abilities' => $standard],
            ['key' => 'backups', 'label' => 'Veritabanı Yedekleri', 'model' => DatabaseBackup::class, 'abilities' => $standard],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function allows(User $user, string $ability, string $modelClass, bool $classLevel): bool
    {
        try {
            return Gate::forUser($user)->allows(
                $ability,
                $classLevel ? $modelClass : $this->representativeModel($modelClass),
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function representativeUser(UserRole $role): User
    {
        $user = new User;
        $user->forceFill([
            'id' => match ($role) {
                UserRole::SystemAdministrator => 9001,
                UserRole::AgencyOwner => 9002,
                UserRole::Editor => 9003,
            },
            'agency_id' => $role === UserRole::SystemAdministrator ? null : 1000,
            'name' => $role->label(),
            'email' => $role->value.'@matrix.local',
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    /** @param class-string<Model> $modelClass */
    private function representativeModel(string $modelClass): Model
    {
        $model = new $modelClass;

        if ($model instanceof Agency) {
            $model->forceFill(['id' => 1000]);

            return $model;
        }

        if ($model instanceof User) {
            $model->forceFill(['id' => 9010, 'agency_id' => 1000, 'role' => UserRole::Editor]);

            return $model;
        }

        $model->forceFill(['id' => 9020, 'agency_id' => 1000]);

        return $model;
    }
}
