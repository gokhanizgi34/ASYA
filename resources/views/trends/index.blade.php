<x-layouts.app title="Trend Motoru">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">GERÇEK ZAMANLI GÜNDEM RADARI</p><h1 class="mt-3 text-4xl font-black">Trend Motoru</h1><p class="mt-2 text-slate-400">Son 24 saatin haber ve SEO sinyallerini önceki dönemle karşılaştırın.</p>@if($lastAnalyzedAt)<p class="mt-2 text-xs text-slate-500">Son analiz: {{ Illuminate\Support\Carbon::parse($lastAnalyzedAt)->format('d.m.Y H:i') }}</p>@endif</div>@can('create', App\Models\TrendTopic::class)<form method="POST" action="{{ route('trends.analyze') }}" class="flex flex-col gap-2 sm:flex-row">@csrf<select name="agency_id" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">@foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select><button class="rounded-xl bg-fuchsia-300 px-5 py-3 font-bold text-slate-950">Analizi yenile</button></form>@endcan</header>
        <div class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h2 class="text-lg font-black">Google Trends günlük kotası</h2>
                    <p class="mt-2 text-sm text-slate-400">Bugün <strong class="text-white">{{ $googleTrendUsedToday }}</strong> / <strong class="text-white">{{ $googleTrendDailyLimit }}</strong> haber alındı. Kota dolunca Google Trends durur; diğer kaynaklar çalışmaya devam eder.</p>
                    <p class="mt-1 text-xs text-slate-500">Trend keşfi Google RSS verisini kullanır; yapay zekâ yalnızca uygun trend habere dönüştürülürken devreye girer.</p>
                </div>
                @can('updateAny', App\Models\SystemSetting::class)
                    <form method="POST" action="{{ route('trends.google-quota') }}" class="flex flex-col gap-2 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        @if(auth()->user()->isSystemAdministrator())
                            <select name="agency_id" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                                @foreach($agencies as $agency)
                                    <option value="{{ $agency->id }}" @selected($settingsAgencyId === $agency->id)>{{ $agency->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="agency_id" value="{{ $settingsAgencyId }}">
                        @endif
                        <input type="number" name="daily_limit" value="{{ $googleTrendDailyLimit }}" min="0" max="100" class="w-28 rounded-xl border border-white/10 bg-slate-900 px-4 py-3" aria-label="Günlük Google Trends haber kotası">
                        <button class="rounded-xl bg-fuchsia-300 px-5 py-3 font-bold text-slate-950 hover:bg-fuchsia-200">Kotayı kaydet</button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach($statuses as $status)<a href="{{ route('trends.index', ['status' => $status->value]) }}" class="rounded-xl border p-4 {{ request('status') === $status->value ? 'border-fuchsia-300/50 bg-fuchsia-300/10' : 'border-white/10 bg-white/[.04]' }}"><span class="text-sm text-slate-400">{{ $status->label() }}</span><strong class="mt-2 block text-2xl">{{ $statusCounts[$status->value] ?? 0 }}</strong></a>@endforeach</div>
        <form method="GET" class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 sm:flex-row"><input name="q" value="{{ request('q') }}" placeholder="Trend konusu ara" class="flex-1 rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-fuchsia-300"><select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3"><option value="">Tüm durumlar</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select><button class="rounded-xl border border-fuchsia-300/30 px-5 py-3 font-bold text-fuchsia-200">Filtrele</button></form>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">@forelse($topics as $topic)<a href="{{ route('trends.show', $topic) }}" class="group rounded-2xl border border-white/10 bg-white/[.04] p-5 transition hover:-translate-y-0.5 hover:border-fuchsia-300/30"><div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-black group-hover:text-fuchsia-200">{{ $topic->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $topic->agency->name }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ match($topic->status) { App\TrendStatus::Hot => 'bg-rose-400/10 text-rose-300', App\TrendStatus::Rising => 'bg-fuchsia-400/10 text-fuchsia-300', App\TrendStatus::Cooling => 'bg-cyan-400/10 text-cyan-300', default => 'bg-slate-400/10 text-slate-300' } }}">{{ $topic->status->label() }}</span></div><div class="mt-5 grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-slate-900/70 p-3"><strong class="block text-xl">{{ $topic->mention_count }}</strong><span class="text-xs text-slate-500">Sinyal</span></div><div class="rounded-xl bg-slate-900/70 p-3"><strong class="block text-xl">{{ $topic->source_count }}</strong><span class="text-xs text-slate-500">Kaynak</span></div><div class="rounded-xl bg-slate-900/70 p-3"><strong class="block text-xl">{{ number_format($topic->score, 0) }}</strong><span class="text-xs text-slate-500">Puan</span></div></div><div class="mt-4 flex items-center justify-between text-sm"><span class="text-slate-500">Hız</span><strong class="{{ $topic->velocity >= 0 ? 'text-emerald-300' : 'text-cyan-300' }}">{{ $topic->velocity >= 0 ? '+' : '' }}{{ number_format($topic->velocity, 1) }}%</strong></div></a>@empty<div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400 md:col-span-2 xl:col-span-3">Henüz yeterli trend sinyali yok. Analizi başlatın veya haber akışını bekleyin.</div>@endforelse</div>
        {{ $topics->links() }}
    </section>
</x-layouts.app>