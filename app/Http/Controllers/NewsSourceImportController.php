<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportNewsSourceRequest;
use App\Models\NewsSource;
use App\Services\AutomaticNewsPipelineStarter;
use App\Services\NewsFeedImporter;
use Illuminate\Http\RedirectResponse;
use Throwable;

class NewsSourceImportController extends Controller
{
    public function __invoke(
        ImportNewsSourceRequest $request,
        NewsSource $newsSource,
        NewsFeedImporter $importer,
        AutomaticNewsPipelineStarter $pipeline,
    ): RedirectResponse {
        try {
            $result = $importer->import($newsSource);
            $batch = $pipeline->start($newsSource, $result['item_ids']);

            $method = match ($result['method']) {
                'rss_atom_xml' => 'RSS / Atom / XML',
                'wordpress_json_api' => 'WordPress JSON API',
                'json_api' => 'JSON API',
                'html_dom_crawl' => 'HTML / DOM tarama',
                'visual_ai_ocr' => 'Ekran görüntüsü / yapay zekâ',
                default => $result['method'],
            };
            $pipelineMessage = $batch ? ' AI haber üretim bandı otomatik başlatıldı.' : '';

            return back()->with('success', "Akıllı alım başarılı ({$method}): {$result['received']} bulundu, {$result['imported']} yeni haber alındı, {$result['skipped']} tekrar atlandı.{$pipelineMessage}");
        } catch (Throwable $exception) {
            $newsSource->forceFill([
                'last_fetched_at' => now(),
                'last_fetch_error' => str($exception->getMessage())->limit(1000),
            ])->save();

            return back()->with('error', 'Kaynak bağlantısı başarısız: '.$exception->getMessage());
        }
    }
}
