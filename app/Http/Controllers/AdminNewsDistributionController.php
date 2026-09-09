<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminNewsDistributionRequest;
use App\Models\AdminNewsDistribution;
use App\Models\AdminNewsDistributionItem;
use App\Models\Agency;
use App\Models\User;
use App\Services\AdminNewsDistributionService;
use App\Services\AdminNewsDistributionXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminNewsDistributionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdministrator($request);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('admin-news-distributions.index', [
            'agencies' => Agency::query()->where('is_active', true)->withCount(['publishingTargets' => fn ($query) => $query->where('is_active', true)])->orderBy('province')->orderBy('name')->get(),
            'provinces' => Agency::query()->where('is_active', true)->whereNotNull('province')->where('province', '!=', '')->distinct()->orderBy('province')->pluck('province'),
            'distributions' => $this->distributionQuery($filters)->paginate(10)->withQueryString(),
        ]);
    }

    public function store(StoreAdminNewsDistributionRequest $request, AdminNewsDistributionService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $distribution = $service->create($request->validated(), $request->file('image'), $user);

        return redirect()->route('admin-news-distributions.index')->with(
            'success',
            $distribution->recipient_agency_count.' ajans ve '.$distribution->publication_count.' WordPress sitesi için haber yayın kuyruğuna alındı.',
        );
    }

    public function export(Request $request, AdminNewsDistributionXlsxExporter $exporter): BinaryFileResponse
    {
        $this->authorizeAdministrator($request);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $distributionIds = $this->distributionQuery($filters)->pluck('id');
        $items = AdminNewsDistributionItem::query()
            ->whereIn('admin_news_distribution_id', $distributionIds)
            ->with(['distribution', 'agency', 'publishingTarget', 'publication'])
            ->orderBy('admin_news_distribution_id')
            ->orderBy('agency_id')
            ->get();
        $path = $exporter->export($items);
        $filename = 'asya-yayin-raporu-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @param array<string, mixed> $filters */
    /** @return Builder<AdminNewsDistribution> */
    private function distributionQuery(array $filters): Builder
    {
        return AdminNewsDistribution::query()
            ->with(['items.agency', 'items.publishingTarget', 'items.publication'])
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest();
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }
}
