<?php

namespace App\Jobs;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\Publication;
use App\PublicationStatus;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InspectPublishedUrlInSearchConsole implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 900];

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $publicationId) {}

    public function uniqueId(): string
    {
        return (string) $this->publicationId;
    }

    public function handle(GoogleSearchConsoleService $searchConsole): void
    {
        $publication = Publication::query()->find($this->publicationId);

        if (! $publication
            || $publication->status !== PublicationStatus::Published
            || blank($publication->remote_url)) {
            return;
        }

        $integration = ApiIntegration::query()
            ->where('agency_id', $publication->agency_id)
            ->where('provider', IntegrationProvider::GoogleSearchConsole)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        if (! $integration) {
            return;
        }

        try {
            $response = $searchConsole->inspectUrl($integration, (string) $publication->remote_url);
            $indexStatus = data_get($response, 'inspectionResult.indexStatusResult', []);
            $this->storeResult($publication, [
                'verdict' => data_get($indexStatus, 'verdict'),
                'coverage_state' => data_get($indexStatus, 'coverageState'),
                'indexing_state' => data_get($indexStatus, 'indexingState'),
                'page_fetch_state' => data_get($indexStatus, 'pageFetchState'),
                'last_crawl_time' => data_get($indexStatus, 'lastCrawlTime'),
                'robots_txt_state' => data_get($indexStatus, 'robotsTxtState'),
                'inspected_at' => now()->toIso8601String(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $this->storeResult($publication, [
                'inspected_at' => now()->toIso8601String(),
                'error' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $result */
    private function storeResult(Publication $publication, array $result): void
    {
        $responseMeta = $publication->response_meta ?? [];
        data_set($responseMeta, 'google_search_console', $result);
        $publication->forceFill(['response_meta' => $responseMeta])->save();
    }
}
