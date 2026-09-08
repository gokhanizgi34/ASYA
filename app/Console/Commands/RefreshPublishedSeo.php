<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\PublicationStatus;
use App\Services\PublicationSeoRefresher;
use Illuminate\Console\Command;

class RefreshPublishedSeo extends Command
{
    protected $signature = 'seo:refresh-published {--target=ilcehaber.com : Hedef alan adı veya ad parçası} {--limit=500 : En fazla yayın sayısı} {--no-dispatch : WordPress güncelleme işi oluşturma}';

    protected $description = 'Yayınlanmış haberlerin SEO verilerini yeniler ve mevcut WordPress yazılarını günceller';

    public function handle(PublicationSeoRefresher $refresher): int
    {
        $target = trim((string) $this->option('target'));
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $processed = 0;

        Publication::query()
            ->with(['article.seoAnalysis', 'article.selectedVisualAsset', 'publishingTarget'])
            ->where('status', PublicationStatus::Published)
            ->whereHas('publishingTarget', fn ($query) => $query
                ->where('name', 'like', '%'.$target.'%')
                ->orWhere('base_url', 'like', '%'.$target.'%'))
            ->orderBy('id')
            ->eachById(function (Publication $publication) use ($refresher, $limit, &$processed): bool {
                if ($processed >= $limit) {
                    return false;
                }

                $refresher->refresh($publication, ! $this->option('no-dispatch'));
                $processed++;
                $this->line('Yenilendi: #'.$publication->id.' '.$publication->article->title);

                return true;
            }, 100);

        $this->info($processed.' yayın için SEO verisi yenilendi.');

        return self::SUCCESS;
    }
}
