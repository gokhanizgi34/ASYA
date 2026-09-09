<?php

namespace App\Services;

use App\ArticleStatus;
use App\CopyrightStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\AdminNewsDistribution;
use App\Models\AdminNewsDistributionItem;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\SeoAnalysis;
use App\Models\User;
use App\Models\VisualAsset;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AdminNewsDistributionService
{
    public function __construct(
        private SeoAnalyzer $seoAnalyzer,
        private PublicationCreator $publicationCreator,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, UploadedFile $image, User $user): AdminNewsDistribution
    {
        $imagePath = $image->store('admin-news-distributions', 'public');
        if ($imagePath === false) {
            throw new RuntimeException('Haber görseli güvenli depolama alanına kaydedilemedi.');
        }

        try {
            [$distribution, $publicationIds] = DB::transaction(function () use ($data, $image, $imagePath, $user): array {
                $agencies = $this->selectedAgencies($data);
                $distribution = AdminNewsDistribution::query()->create([
                    'created_by' => $user->id,
                    'title' => $data['title'],
                    'summary' => $data['summary'],
                    'body' => $data['body'],
                    'image_disk' => 'public',
                    'image_path' => $imagePath,
                    'image_original_name' => $image->getClientOriginalName(),
                    'image_mime_type' => $image->getMimeType(),
                    'selection_mode' => $data['selection_mode'],
                    'province' => $data['selection_mode'] === 'province' ? $data['province'] : null,
                    'recipient_agency_count' => $agencies->count(),
                ]);

                $publicationIds = [];

                foreach ($agencies as $agency) {
                    $article = $this->createArticle($distribution, $agency, $user, $imagePath, $image);
                    $targets = $agency->publishingTargets()->where('is_active', true)->get();

                    if ($targets->isEmpty()) {
                        AdminNewsDistributionItem::query()->create([
                            'admin_news_distribution_id' => $distribution->id,
                            'agency_id' => $agency->id,
                            'article_id' => $article->id,
                            'failure_message' => 'Ajansın aktif WordPress hedefi bulunmuyor.',
                        ]);

                        continue;
                    }

                    foreach ($targets as $target) {
                        $publication = $this->publicationCreator->create([
                            'agency_id' => $agency->id,
                            'article_id' => $article->id,
                            'publishing_target_id' => $target->id,
                            'remote_status' => RemotePublicationStatus::Publish,
                            'remote_author_id' => null,
                            'remote_category_ids' => [],
                            'remote_tag_ids' => [],
                            'scheduled_for' => null,
                            'schedule_timezone' => config('app.timezone'),
                        ], $user);
                        $payload = $publication->payload;
                        $payload['preserve_content'] = true;
                        $payload['skip_duplicate_check'] = true;
                        $payload['admin_news_distribution_id'] = $distribution->id;
                        $publication->forceFill(['payload' => $payload])->save();

                        AdminNewsDistributionItem::query()->create([
                            'admin_news_distribution_id' => $distribution->id,
                            'agency_id' => $agency->id,
                            'publishing_target_id' => $target->id,
                            'article_id' => $article->id,
                            'publication_id' => $publication->id,
                        ]);
                        $publicationIds[] = $publication->id;
                    }
                }

                $distribution->forceFill(['publication_count' => count($publicationIds)])->save();

                return [$distribution, $publicationIds];
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        foreach ($publicationIds as $publicationId) {
            PublishArticleToWordPress::dispatch($publicationId)->onQueue('publishing')->afterCommit();
        }

        return $distribution;
    }

    /** @param array<string, mixed> $data
     *  @return Collection<int, Agency>
     */
    private function selectedAgencies(array $data): Collection
    {
        return Agency::query()
            ->where('is_active', true)
            ->when($data['selection_mode'] === 'province', fn ($query) => $query->where('province', $data['province']))
            ->when($data['selection_mode'] === 'selected', fn ($query) => $query->whereKey($data['agency_ids']))
            ->orderBy('name')
            ->lockForUpdate()
            ->get();
    }

    private function createArticle(AdminNewsDistribution $distribution, Agency $agency, User $user, string $imagePath, UploadedFile $image): Article
    {
        $article = Article::query()->create([
            'agency_id' => $agency->id,
            'author_id' => $user->id,
            'title' => $distribution->title,
            'slug' => Str::slug($distribution->title).'-yonetim-'.$distribution->id.'-'.$agency->id,
            'summary' => $distribution->summary,
            'body' => $distribution->body,
            'editorial_metadata' => ['admin_news_distribution_id' => $distribution->id, 'category' => $agency->category_name],
            'status' => ArticleStatus::Published,
            'source_trust_status' => SourceTrustStatus::Verified,
            'source_name' => 'ASYA Sistem Yönetimi',
            'source_url' => null,
            'published_at' => now(),
        ]);

        SeoAnalysis::query()->create([
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            ...$this->seoAnalyzer->analyze($article),
        ]);

        $dimensions = @getimagesize($image->getRealPath());
        VisualAsset::query()->create([
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            'uploaded_by' => $user->id,
            'title' => $distribution->title,
            'source_type' => VisualSourceType::Upload,
            'status' => VisualAssetStatus::Approved,
            'copyright_status' => CopyrightStatus::Original,
            'storage_disk' => 'public',
            'storage_path' => $imagePath,
            'mime_type' => $image->getMimeType(),
            'width' => is_array($dimensions) ? $dimensions[0] : null,
            'height' => is_array($dimensions) ? $dimensions[1] : null,
            'quality_score' => 100,
            'alt_text' => Str::limit($distribution->title, 255, ''),
            'is_selected' => true,
            'evaluated_at' => now(),
        ]);

        return $article->load(['agency', 'seoAnalysis', 'selectedVisualAsset']);
    }
}
