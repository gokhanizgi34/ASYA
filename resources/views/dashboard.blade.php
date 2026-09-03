<x-layouts.app title="Gösterge Paneli">
    <section class="space-y-8">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div class="space-y-3">
                <p class="text-sm font-bold tracking-[.18em] text-cyan-300">KOMUTA MERKEZİ</p>
                <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Günaydın, {{ auth()->user()->name }}.</h1>
                <p class="max-w-2xl text-slate-400">{{ auth()->user()->agency?->name ?? 'Tüm sistem' }} için operasyon, kaynak ve altyapı durumunu tek ekrandan izleyin.</p>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] px-4 py-3 text-sm">
                <span class="relative flex h-3 w-3"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-400"></span></span>
                <span><strong class="block">Canlı sistem görünümü</strong><small class="text-slate-400">{{ $metrics['generated_at']->format('d.m.Y H:i:s') }}</small></span>
            </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-white/10 bg-gradient-to-br from-cyan-400/10 to-transparent p-5"><div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-cyan-300/15 text-xl text-cyan-200">✦</span><span class="text-xs font-bold text-cyan-200">SON 24 SAAT</span></div><strong class="mt-5 block text-4xl font-black">{{ number_format($metrics['summary']['articles_last_24_hours'], 0, ',', '.') }}</strong><span class="mt-1 block text-sm text-slate-400">Üretilen haber</span></article>
            <article class="rounded-2xl border border-white/10 bg-gradient-to-br from-emerald-400/10 to-transparent p-5"><div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-300/15 text-xl text-emerald-200">⌁</span><span class="text-xs font-bold text-emerald-200">KAYNAKLAR</span></div><strong class="mt-5 block text-4xl font-black">{{ $metrics['summary']['active_sources'] }}<small class="text-lg text-slate-500"> / {{ $metrics['summary']['total_sources'] }}</small></strong><span class="mt-1 block text-sm text-slate-400">Aktif veri kaynağı</span></article>
            <article class="rounded-2xl border border-white/10 bg-gradient-to-br from-indigo-400/10 to-transparent p-5"><div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-300/15 text-xl text-indigo-200">◇</span><span class="text-xs font-bold text-indigo-200">ENTEGRASYON</span></div><strong class="mt-5 block text-4xl font-black">{{ $metrics['summary']['api_integrations'] }}</strong><span class="mt-1 block text-sm text-slate-400">Bağlı API sağlayıcısı</span></article>
            <article class="rounded-2xl border border-white/10 bg-gradient-to-br from-amber-400/10 to-transparent p-5"><div class="flex items-start justify-between gap-4"><span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-300/15 text-xl text-amber-200">↻</span><span class="text-xs font-bold text-amber-200">KUYRUK</span></div><strong class="mt-5 block text-4xl font-black">{{ $metrics['summary']['pending_jobs'] }}</strong><span class="mt-1 block text-sm text-slate-400">Bekleyen görev · {{ $metrics['summary']['failed_jobs'] }} hatalı</span></article>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.45fr_.55fr]">
            <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold">7 günlük haber üretimi</h2><p class="mt-1 text-sm text-slate-400">Günlük sisteme alınan içerik hacmi</p></div><span class="rounded-full border border-white/10 px-3 py-1 text-xs text-slate-400">CANLI</span></div>
                <div class="mt-8 grid h-56 grid-cols-7 items-end gap-3 border-b border-white/10 px-2" role="img" aria-label="Son yedi günlük haber üretim grafiği">
                    @foreach ($metrics['article_chart'] as $day)
                        <div class="flex h-full flex-col justify-end gap-2 text-center"><span class="text-xs font-bold text-slate-300">{{ $day['value'] }}</span><div class="min-h-1 rounded-t-lg bg-gradient-to-t from-cyan-500 to-cyan-200 transition-all" style="height: {{ max(3, $day['percent']) }}%"></div><span class="pb-3 text-xs text-slate-500">{{ $day['label'] }}</span></div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6">
                <div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-bold">Sistem sağlığı</h2><p class="mt-1 text-sm text-slate-400">Yerel çalışma ortamı</p></div><span class="h-3 w-3 rounded-full {{ $metrics['health']['status'] === 'healthy' ? 'bg-emerald-400 shadow-lg shadow-emerald-400/50' : 'bg-amber-400 shadow-lg shadow-amber-400/50' }}"></span></div>
                <dl class="mt-6 divide-y divide-white/10 text-sm">
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">Veritabanı</dt><dd class="font-bold {{ $metrics['health']['database'] ? 'text-emerald-300' : 'text-rose-300' }}">{{ $metrics['health']['database'] ? 'Bağlı' : 'Bağlantı yok' }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">PHP belleği</dt><dd class="font-bold">{{ $metrics['health']['php_memory'] }} / {{ $metrics['health']['php_memory_limit'] }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">Boş disk</dt><dd class="font-bold">{{ $metrics['health']['disk_free'] }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">CPU yükü</dt><dd class="font-bold">{{ $metrics['health']['cpu_load'] ?? 'Yerelde ölçülemiyor' }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">Ortam</dt><dd class="font-bold uppercase">{{ $metrics['health']['environment'] }}</dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-400">Saat dilimi</dt><dd class="font-bold">{{ $metrics['health']['timezone'] }}</dd></div>
                </dl>
            </article>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6">
                <div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-bold">Uyarı merkezi</h2><p class="mt-1 text-sm text-slate-400">Müdahale gerektiren operasyonlar</p></div><span class="rounded-full bg-white/5 px-3 py-1 text-xs font-bold">{{ count($metrics['alerts']) }}</span></div>
                <div class="mt-6 space-y-3">
                    @foreach ($metrics['alerts'] as $alert)
                        <div class="rounded-xl border p-4 {{ match($alert['level']) { 'danger' => 'border-rose-400/20 bg-rose-400/10', 'warning' => 'border-amber-400/20 bg-amber-400/10', 'success' => 'border-emerald-400/20 bg-emerald-400/10', default => 'border-cyan-400/20 bg-cyan-400/10' } }}"><strong class="block">{{ $alert['title'] }}</strong><p class="mt-1 text-sm text-slate-300">{{ $alert['message'] }}</p></div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6">
                <div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-bold">Son kullanıcılar</h2><p class="mt-1 text-sm text-slate-400">Ekibe en son katılan hesaplar</p></div>@can('viewAny', App\Models\User::class)<a href="{{ route('users.index') }}" class="text-sm font-bold text-cyan-300 hover:text-cyan-200">Tümünü gör →</a>@endcan</div>
                <div class="mt-6 divide-y divide-white/10">
                    @forelse ($metrics['recent_users'] as $recentUser)
                        <div class="flex items-center justify-between gap-4 py-3"><div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white/10 font-bold text-cyan-200">{{ mb_strtoupper(mb_substr($recentUser->name, 0, 1)) }}</span><span class="min-w-0"><strong class="block truncate">{{ $recentUser->name }}</strong><small class="block truncate text-slate-500">{{ $recentUser->agency?->name ?? 'Sistem geneli' }}</small></span></div><span class="shrink-0 text-xs text-slate-500">{{ $recentUser->created_at->diffForHumans() }}</span></div>
                    @empty
                        <p class="py-10 text-center text-sm text-slate-500">Henüz kullanıcı bulunmuyor.</p>
                    @endforelse
                </div>
            </article>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @can('viewAny', App\Models\Agency::class)<a href="{{ route('agencies.index') }}" class="group rounded-2xl border border-indigo-300/20 bg-indigo-300/10 p-5 hover:border-indigo-300/50"><span class="text-sm text-indigo-200">{{ $metrics['summary']['agencies'] }} ajans</span><strong class="mt-2 block text-lg">Ajans yönetimine git <span class="inline-block transition-transform group-hover:translate-x-1">→</span></strong></a>@endcan
            @can('viewAny', App\Models\User::class)<a href="{{ route('users.index') }}" class="group rounded-2xl border border-cyan-300/20 bg-cyan-300/10 p-5 hover:border-cyan-300/50"><span class="text-sm text-cyan-200">{{ $metrics['summary']['users'] }} kullanıcı</span><strong class="mt-2 block text-lg">Ekip yönetimine git <span class="inline-block transition-transform group-hover:translate-x-1">→</span></strong></a>@endcan
        </div>
    </section>
</x-layouts.app>