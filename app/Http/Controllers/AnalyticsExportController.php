<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsReportRequest;
use App\Models\AnalyticsSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
    public function __invoke(AnalyticsReportRequest $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        $query = AnalyticsSnapshot::query()->visibleTo($user)->whereDate('report_date', '>=', $data['from'])->whereDate('report_date', '<=', $data['to'])->when($data['agency_id'] ?? null, fn ($query, $agencyId) => $query->where('agency_id', $agencyId))->with('agency')->orderBy('report_date')->orderBy('agency_id');

        return Response::streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tarih', 'Ajans', 'Ham Haber', 'Oluşturulan Haber', 'Yayınlanan Haber', 'Başarılı Yayın', 'Hatalı Yayın', 'Başarı Oranı %', 'Kampanya', 'Kampanya İçeriği', 'Trend Konusu', 'SEO Kelime', 'Ort. SEO', 'Ort. Trend'], ';');
            foreach ($query->lazy(200) as $snapshot) {
                fputcsv($output, [$snapshot->report_date->toDateString(), $this->safeCell($snapshot->agency->name), $snapshot->raw_news_count, $snapshot->articles_created_count, $snapshot->articles_published_count, $snapshot->publication_success_count, $snapshot->publication_failure_count, $snapshot->publicationSuccessRate(), $snapshot->campaigns_created_count, $snapshot->campaign_contents_count, $snapshot->trend_topics_count, $snapshot->seo_word_count, $snapshot->average_seo_score, $snapshot->average_trend_score], ';');
            }
            fclose($output);
        }, 'asya-analitik-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
