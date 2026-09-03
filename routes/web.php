<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AgencyMailSettingController;
use App\Http\Controllers\AgencyMailTestController;
use App\Http\Controllers\AgencyStatusController;
use App\Http\Controllers\AiColumnistController;
use App\Http\Controllers\AiPromptController;
use App\Http\Controllers\AiVisualEngineStatusController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsExportController;
use App\Http\Controllers\AnalyticsRefreshController;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\ApiIntegrationTestController;
use App\Http\Controllers\ArticleBulkActionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AuthorizationMatrixController;
use App\Http\Controllers\BlacklistRuleController;
use App\Http\Controllers\CampaignContentController;
use App\Http\Controllers\CampaignContentStatusController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignStatusController;
use App\Http\Controllers\ColumnistDraftController;
use App\Http\Controllers\ContentBatchController;
use App\Http\Controllers\ContentBatchDispatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\DatabaseBackupDownloadController;
use App\Http\Controllers\DatabaseBackupVerifyController;
use App\Http\Controllers\EditorialCalendarGenerationController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\ErrorLogStatusController;
use App\Http\Controllers\FailedPublicationBulkDispatchController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GoogleTrendQuotaController;
use App\Http\Controllers\HoroscopeDayController;
use App\Http\Controllers\HoroscopeForecastController;
use App\Http\Controllers\LearnedRouteController;
use App\Http\Controllers\LearnedRouteStatusController;
use App\Http\Controllers\MultiSiteDistributionController;
use App\Http\Controllers\NewsSourceImportController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\NotificationReadAllController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\PromptSimulationController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\PublicationDispatchController;
use App\Http\Controllers\PublishingTargetController;
use App\Http\Controllers\RawNewsAllActionController;
use App\Http\Controllers\RawNewsBulkActionController;
use App\Http\Controllers\RawNewsItemController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ScheduleEntryController;
use App\Http\Controllers\ScheduleEntryStatusController;
use App\Http\Controllers\SeoAnalysisController;
use App\Http\Controllers\SocialFeedImportController;
use App\Http\Controllers\SocialFeedSourceController;
use App\Http\Controllers\SocialListeningController;
use App\Http\Controllers\SocialMentionController;
use App\Http\Controllers\SocialPostDispatchController;
use App\Http\Controllers\SocialPublishingController;
use App\Http\Controllers\SourceTrustController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TaxonomyMappingController;
use App\Http\Controllers\TrendAnalysisController;
use App\Http\Controllers\TrendTopicController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserStatusController;
use App\Http\Controllers\VisualAssetController;
use App\Http\Controllers\VisualAssetEvaluationController;
use App\Http\Controllers\VisualAssetFileController;
use App\Http\Controllers\VisualAssetSelectionController;
use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return Auth::check() ? to_route('dashboard') : to_route('login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/giris', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/giris', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', EnsureUserIsActive::class, ApplySystemSettings::class])->group(function (): void {
    Route::get('/panel', DashboardController::class)->name('dashboard');
    Route::post('/cikis', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::patch('/ham-haber-havuzu/tumu', RawNewsAllActionController::class)->name('raw-news.all-action');
    Route::patch('/ham-haber-havuzu/toplu-islem', RawNewsBulkActionController::class)->name('raw-news.bulk-action');
    Route::resource('ham-haber-havuzu', RawNewsItemController::class)
        ->parameters(['ham-haber-havuzu' => 'rawNewsItem'])
        ->except(['edit', 'update'])
        ->names('raw-news');

    Route::patch('/haberler/toplu-islem', ArticleBulkActionController::class)->name('articles.bulk-action');
    Route::get('/haberler/{article}/seo', [SeoAnalysisController::class, 'show'])->name('seo.show');
    Route::post('/haberler/{article}/seo', [SeoAnalysisController::class, 'analyze'])->name('seo.analyze');

    Route::resource('haberler', ArticleController::class)
        ->parameters(['haberler' => 'article'])
        ->names('articles');

    Route::post('/tarifler/{recipe}/yayin-merkezine-gonder', [RecipeController::class, 'publish'])->name('recipes.publish');
    Route::post('/tarifler/uret', [RecipeController::class, 'generate'])->name('recipes.generate');
    Route::resource('tarifler', RecipeController::class)
        ->parameters(['tarifler' => 'recipe'])
        ->names('recipes');

    Route::post('/burclar/gun-hazirla', HoroscopeDayController::class)->name('horoscopes.day');
    Route::get('/burclar', [HoroscopeForecastController::class, 'index'])->name('horoscopes.index');
    Route::get('/burclar/{horoscopeForecast}/duzenle', [HoroscopeForecastController::class, 'edit'])->name('horoscopes.edit');
    Route::put('/burclar/{horoscopeForecast}', [HoroscopeForecastController::class, 'update'])->name('horoscopes.update');


    Route::get('/ai-kose-yazarlari/taslaklar/yeni', [ColumnistDraftController::class, 'create'])->name('columnist-drafts.create');
    Route::post('/ai-kose-yazarlari/taslaklar', [ColumnistDraftController::class, 'store'])->name('columnist-drafts.store');
    Route::get('/ai-kose-yazarlari/taslaklar/{columnistDraft}', [ColumnistDraftController::class, 'show'])->name('columnist-drafts.show');
    Route::put('/ai-kose-yazarlari/taslaklar/{columnistDraft}', [ColumnistDraftController::class, 'update'])->name('columnist-drafts.update');
    Route::resource('ai-kose-yazarlari', AiColumnistController::class)
        ->parameters(['ai-kose-yazarlari' => 'aiColumnist'])
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->names('ai-columnists');

    Route::get('/promptlar/{aiPrompt}/simulasyon', [PromptSimulationController::class, 'show'])->name('prompts.simulation');
    Route::post('/promptlar/{aiPrompt}/simulasyon', [PromptSimulationController::class, 'simulate'])->name('prompts.simulation.run');
    Route::resource('promptlar', AiPromptController::class)
        ->parameters(['promptlar' => 'aiPrompt'])
        ->except('show')
        ->names('prompts');

    Route::patch('/kampanyalar/{campaign}/durum', CampaignStatusController::class)->name('campaigns.status');
    Route::post('/kampanyalar/{campaign}/icerikler', [CampaignContentController::class, 'store'])->name('campaign-contents.store');
    Route::put('/kampanyalar/{campaign}/icerikler/{campaignContent}', [CampaignContentController::class, 'update'])->name('campaign-contents.update');
    Route::delete('/kampanyalar/{campaign}/icerikler/{campaignContent}', [CampaignContentController::class, 'destroy'])->name('campaign-contents.destroy');
    Route::patch('/kampanyalar/{campaign}/icerikler/{campaignContent}/durum', CampaignContentStatusController::class)->name('campaign-contents.status');
    Route::resource('kampanyalar', CampaignController::class)->parameters(['kampanyalar' => 'campaign'])->names('campaigns');

    Route::post('/icerik-fabrikasi/{contentBatch}/kuyruga-gonder', ContentBatchDispatchController::class)->name('content-batches.dispatch');
    Route::resource('icerik-fabrikasi', ContentBatchController::class)
        ->parameters(['icerik-fabrikasi' => 'contentBatch'])
        ->only(['index', 'create', 'store', 'show'])
        ->names('content-batches');

    Route::get('/yayin-merkezi/coklu-dagitim', [MultiSiteDistributionController::class, 'create'])->name('publications.multi-site');
    Route::post('/yayin-merkezi/coklu-dagitim', [MultiSiteDistributionController::class, 'store'])->name('publications.multi-site.store');
    Route::post('/yayin-merkezi/hatalilari-yeniden-gonder', FailedPublicationBulkDispatchController::class)->name('publications.dispatch-failed');
    Route::post('/yayin-merkezi/{publication}/yeniden-gonder', PublicationDispatchController::class)->name('publications.dispatch');
    Route::resource('yayin-merkezi', PublicationController::class)
        ->parameters(['yayin-merkezi' => 'publication'])
        ->only(['index', 'create', 'store', 'show'])
        ->names('publications');
    Route::resource('yayin-hedefleri', PublishingTargetController::class)
        ->parameters(['yayin-hedefleri' => 'publishingTarget'])
        ->except('show')
        ->names('publishing-targets');

    Route::resource('kara-liste', BlacklistRuleController::class)
        ->parameters(['kara-liste' => 'blacklistRule'])
        ->except('show')
        ->names('blacklist-rules');

    Route::resource('taksonomi-eslestirmeleri', TaxonomyMappingController::class)
        ->parameters(['taksonomi-eslestirmeleri' => 'taxonomyMapping'])
        ->except('show')
        ->names('taxonomy-mappings');
    Route::get('/sss', FaqController::class)->name('faq.index');
    Route::resource('destek-talepleri', SupportTicketController::class)->parameters(['destek-talepleri' => 'supportTicket'])->only(['index', 'create', 'store', 'show', 'update'])->names('support-tickets');
    Route::post('/entegrasyonlar/mail-ayarlari', [AgencyMailSettingController::class, 'store'])->name('agency-mail-settings.store');
    Route::post('/entegrasyonlar/mail-ayarlari/{agencyMailSetting}/test', AgencyMailTestController::class)->name('agency-mail-settings.test');
    Route::post('/entegrasyonlar/{apiIntegration}/test', ApiIntegrationTestController::class)->name('api-integrations.test');
    Route::resource('entegrasyonlar', ApiIntegrationController::class)
        ->parameters(['entegrasyonlar' => 'apiIntegration'])
        ->except('show')
        ->names('api-integrations');
    Route::get('/rota-ogrenici', LearnedRouteController::class)->name('learned-routes.index');
    Route::patch('/rota-ogrenici/{learnedRoute}/durum', LearnedRouteStatusController::class)->name('learned-routes.status');
    Route::get('/yetki-matrisi', AuthorizationMatrixController::class)->name('authorization-matrix');
    Route::get('/veritabani-yedekleri/{databaseBackup}/indir', DatabaseBackupDownloadController::class)->name('database-backups.download');
    Route::post('/veritabani-yedekleri/{databaseBackup}/dogrula', DatabaseBackupVerifyController::class)->name('database-backups.verify');
    Route::resource('veritabani-yedekleri', DatabaseBackupController::class)
        ->parameters(['veritabani-yedekleri' => 'databaseBackup'])
        ->only(['index', 'store', 'destroy'])
        ->names('database-backups');
    Route::get('/sistem-ayarlari', [SystemSettingController::class, 'index'])->name('system-settings.index');
    Route::put('/sistem-ayarlari', [SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::get('/hata-kayitlari', [ErrorLogController::class, 'index'])->name('error-logs.index');
    Route::get('/hata-kayitlari/{errorLog}', [ErrorLogController::class, 'show'])->name('error-logs.show');
    Route::patch('/hata-kayitlari/{errorLog}/durum', ErrorLogStatusController::class)->name('error-logs.status');
    Route::get('/bildirimler', NotificationCenterController::class)->name('notifications.index');
    Route::patch('/bildirimler/tumunu-oku', NotificationReadAllController::class)->name('notifications.read-all');
    Route::patch('/bildirimler/{systemNotification}/oku', NotificationReadController::class)->name('notifications.read');
    Route::get('/analitik', AnalyticsController::class)->name('analytics.index');
    Route::post('/analitik/yenile', AnalyticsRefreshController::class)->name('analytics.refresh');
    Route::get('/analitik/disari-aktar', AnalyticsExportController::class)->name('analytics.export');

    Route::post('/planlama/ozel-gunler/ai-olustur', EditorialCalendarGenerationController::class)->name('editorial-calendar.generate');
    Route::patch('/planlama/{schedule}/durum', ScheduleEntryStatusController::class)->name('schedules.status');
    Route::resource('planlama', ScheduleEntryController::class)
        ->parameters(['planlama' => 'schedule'])
        ->only(['index', 'create', 'store', 'show'])
        ->names('schedules');

    Route::get('/kaynak-guven', [SourceTrustController::class, 'index'])->name('source-trust.index');
    Route::post('/kaynak-guven/kaynaklar', [SourceTrustController::class, 'storeSource'])->name('source-trust.sources.store');
    Route::put('/kaynak-guven/kaynaklar/{newsSource}', [SourceTrustController::class, 'update'])->name('source-trust.sources.update');
    Route::delete('/kaynak-guven/kaynaklar/{newsSource}', [SourceTrustController::class, 'destroy'])->name('source-trust.sources.destroy');
    Route::post('/kaynak-guven/kaynaklar/{newsSource}/degerlendirmeler', [SourceTrustController::class, 'storeAssessment'])->name('source-trust.assessments.store');
    Route::post('/kaynak-guven/kaynaklar/{newsSource}/haberleri-cek', NewsSourceImportController::class)->name('source-trust.sources.import');

    Route::get('/sosyal-yayinci', [SocialPublishingController::class, 'index'])->name('social-publishing.index');
    Route::post('/sosyal-yayinci/hesaplar', [SocialPublishingController::class, 'storeAccount'])->name('social-publishing.accounts.store');
    Route::post('/sosyal-yayinci/gonderiler', [SocialPublishingController::class, 'storePost'])->name('social-publishing.posts.store');
    Route::post('/sosyal-yayinci/gonderiler/{socialPost}/yayinla', SocialPostDispatchController::class)->name('social-posts.dispatch');

    Route::get('/sosyal-akislar', [SocialFeedSourceController::class, 'index'])->name('social-feed-sources.index');
    Route::post('/sosyal-akislar', [SocialFeedSourceController::class, 'store'])->name('social-feed-sources.store');
    Route::post('/sosyal-akislar/{socialFeedSource}/ice-aktar', SocialFeedImportController::class)->name('social-feed-imports.store');

    Route::get('/sosyal-dinleme', [SocialListeningController::class, 'index'])->name('social-listening.index');
    Route::post('/sosyal-dinleme/kurallar', [SocialListeningController::class, 'store'])->name('social-listening.store');
    Route::post('/sosyal-dinleme/bahisler', [SocialMentionController::class, 'store'])->name('social-mentions.store');
    Route::patch('/sosyal-dinleme/bahisler/{socialMention}', [SocialMentionController::class, 'update'])->name('social-mentions.update');

    Route::patch('/trend-motoru/google-kotasi', GoogleTrendQuotaController::class)->name('trends.google-quota');
    Route::post('/trend-motoru/analiz', TrendAnalysisController::class)->name('trends.analyze');
    Route::resource('trend-motoru', TrendTopicController::class)
        ->parameters(['trend-motoru' => 'trendTopic'])
        ->only(['index', 'show'])
        ->names('trends');

    Route::patch('/gorsel-motoru/ai-durumu', AiVisualEngineStatusController::class)->name('visual-assets.ai-status');
    Route::get('/gorsel-motoru/{visualAsset}/dosya', VisualAssetFileController::class)->name('visual-assets.file');
    Route::post('/gorsel-motoru/{visualAsset}/degerlendir', VisualAssetEvaluationController::class)->name('visual-assets.evaluate');
    Route::patch('/gorsel-motoru/{visualAsset}/kapak-sec', VisualAssetSelectionController::class)->name('visual-assets.select');
    Route::resource('gorsel-motoru', VisualAssetController::class)
        ->parameters(['gorsel-motoru' => 'visualAsset'])
        ->only(['index', 'create', 'store', 'show', 'destroy'])
        ->names('visual-assets');

    Route::resource('ajanslar', AgencyController::class)
        ->parameters(['ajanslar' => 'agency'])
        ->except(['show', 'destroy'])
        ->names('agencies');
    Route::patch('/ajanslar/{agency}/durum', AgencyStatusController::class)->name('agencies.status.update');

    Route::resource('kullanicilar', UserController::class)
        ->parameters(['kullanicilar' => 'user'])
        ->except(['show', 'destroy'])
        ->names('users');
    Route::patch('/kullanicilar/{user}/durum', [UserStatusController::class, 'update'])
        ->name('users.status.update');
});
