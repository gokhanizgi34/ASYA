<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $metrics = $this->buildMetrics($user);

        return view('dashboard', compact('metrics'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetrics(User $user): array
    {
        $articleMetrics = $this->articleMetrics($user);
        $sourceMetrics = $this->sourceMetrics($user);
        $agencyQuery = Agency::query();
        $userQuery = User::query();

        if (! $user->isSystemAdministrator()) {
            $agencyQuery->whereKey($user->agency_id);
            $userQuery->where('agency_id', $user->agency_id);
        }

        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $inactiveAgencies = (clone $agencyQuery)->where('is_active', false)->count();
        $expiringSubscriptions = (clone $agencyQuery)
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [today(), today()->addDays(30)])
            ->count();

        return [
            'summary' => [
                'articles_last_24_hours' => $articleMetrics['last_24_hours'],
                'active_sources' => $sourceMetrics['active'],
                'total_sources' => $sourceMetrics['total'],
                'failed_sources' => $sourceMetrics['failed'],
                'api_integrations' => $this->tableCount('api_integrations', $user),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'agencies' => $agencyQuery->count(),
                'users' => $userQuery->count(),
            ],
            'article_chart' => $articleMetrics['chart'],
            'health' => $this->healthMetrics($failedJobs),
            'alerts' => $this->alerts($sourceMetrics, $failedJobs, $inactiveAgencies, $expiringSubscriptions),
            'recent_users' => $userQuery->with('agency')->latest()->limit(5)->get(),
            'generated_at' => now(),
        ];
    }

    /**
     * @return array{last_24_hours: int, chart: array<int, array{label: string, value: int, percent: int}>}
     */
    private function articleMetrics(User $user): array
    {
        if (! Schema::hasTable('articles')) {
            return [
                'last_24_hours' => 0,
                'chart' => $this->emptyArticleChart(),
            ];
        }

        $lastDayQuery = $this->tenantQuery('articles', $user);
        $lastDayCount = $lastDayQuery->where('created_at', '>=', now()->subDay())->count();
        $rawChart = [];

        foreach (range(6, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);
            $count = $this->tenantQuery('articles', $user)
                ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->count();
            $rawChart[] = ['label' => $date->translatedFormat('D'), 'value' => $count];
        }

        $maximum = max(1, ...array_column($rawChart, 'value'));
        $chart = array_map(fn (array $item): array => [
            ...$item,
            'percent' => (int) round(($item['value'] / $maximum) * 100),
        ], $rawChart);

        return ['last_24_hours' => $lastDayCount, 'chart' => $chart];
    }

    /**
     * @return array{total: int, active: int, failed: int, available: bool}
     */
    private function sourceMetrics(User $user): array
    {
        if (! Schema::hasTable('news_sources')) {
            return ['total' => 0, 'active' => 0, 'failed' => 0, 'available' => false];
        }

        $total = $this->tenantQuery('news_sources', $user)->count();
        $active = Schema::hasColumn('news_sources', 'is_active')
            ? $this->tenantQuery('news_sources', $user)->where('is_active', true)->count()
            : $total;
        $failed = Schema::hasColumn('news_sources', 'last_fetch_error')
            ? $this->tenantQuery('news_sources', $user)->whereNotNull('last_fetch_error')->count()
            : 0;

        return compact('total', 'active', 'failed') + ['available' => true];
    }

    /**
     * @return array<int, array{label: string, value: int, percent: int}>
     */
    private function emptyArticleChart(): array
    {
        return array_map(fn (int $daysAgo): array => [
            'label' => today()->subDays($daysAgo)->translatedFormat('D'),
            'value' => 0,
            'percent' => 0,
        ], range(6, 0));
    }

    /**
     * @return array{database: bool, php_memory: string, php_memory_limit: string, disk_free: string, cpu_load: ?float, environment: string, timezone: string}
     */
    private function healthMetrics(int $failedJobs): array
    {
        try {
            DB::select('select 1');
            $databaseIsAvailable = true;
        } catch (Throwable) {
            $databaseIsAvailable = false;
        }

        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $freeDiskSpace = disk_free_space(base_path());

        return [
            'database' => $databaseIsAvailable,
            'php_memory' => $this->formatBytes(memory_get_usage(true)),
            'php_memory_limit' => (string) ini_get('memory_limit'),
            'disk_free' => is_float($freeDiskSpace) ? $this->formatBytes((int) $freeDiskSpace) : 'Bilinmiyor',
            'cpu_load' => is_array($loadAverage) ? round((float) $loadAverage[0], 2) : null,
            'environment' => app()->environment(),
            'timezone' => (string) config('app.timezone'),
            'status' => $databaseIsAvailable && $failedJobs === 0 ? 'healthy' : 'attention',
        ];
    }

    /**
     * @param  array{total: int, active: int, failed: int, available: bool}  $sourceMetrics
     * @return array<int, array{level: string, title: string, message: string}>
     */
    private function alerts(array $sourceMetrics, int $failedJobs, int $inactiveAgencies, int $expiringSubscriptions): array
    {
        $alerts = [];

        if (! $sourceMetrics['available']) {
            $alerts[] = ['level' => 'info', 'title' => 'Kaynak modülü bekleniyor', 'message' => 'Kaynak siteler tanımlandığında sağlık oranları burada görünecek.'];
        } elseif ($sourceMetrics['failed'] > 0) {
            $alerts[] = ['level' => 'danger', 'title' => 'Kaynak hatası', 'message' => "{$sourceMetrics['failed']} kaynak hata durumunda."];
        }

        if ($failedJobs > 0) {
            $alerts[] = ['level' => 'danger', 'title' => 'Başarısız kuyruk görevi', 'message' => "{$failedJobs} görev incelenmeyi bekliyor."];
        }

        if ($inactiveAgencies > 0) {
            $alerts[] = ['level' => 'warning', 'title' => 'Pasif ajans', 'message' => "{$inactiveAgencies} ajans pasif durumda."];
        }

        if ($expiringSubscriptions > 0) {
            $alerts[] = ['level' => 'warning', 'title' => 'Yaklaşan abonelik bitişi', 'message' => "{$expiringSubscriptions} ajansın aboneliği 30 gün içinde bitiyor."];
        }

        if ($alerts === []) {
            $alerts[] = ['level' => 'success', 'title' => 'Sistem sağlıklı', 'message' => 'Şu anda müdahale gerektiren bir durum bulunmuyor.'];
        }

        return $alerts;
    }

    private function tableCount(string $table, User $user): int
    {
        return Schema::hasTable($table) ? $this->tenantQuery($table, $user)->count() : 0;
    }

    private function tenantQuery(string $table, User $user): Builder
    {
        $query = DB::table($table);

        if (! $user->isSystemAdministrator() && Schema::hasColumn($table, 'agency_id')) {
            $query->where('agency_id', $user->agency_id);
        }

        return $query;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
