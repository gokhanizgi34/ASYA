<x-layouts.app title="Rota ve Metot Öğrenici">
    <section class="space-y-7">
        <header>
            <p class="text-sm font-bold tracking-[.18em] text-cyan-300">ENTEGRASYON HAFIZASI</p>
            <h1 class="mt-3 text-4xl font-black">Rota ve Metot Öğrenici</h1>
            <p class="mt-2 max-w-3xl text-slate-400">Gerçek entegrasyon çağrılarından, sorgu parametrelerini ve kimlik bilgilerini saklamadan çalışan API yollarını öğrenir.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Öğrenilen rota', 'value' => $summary['total'], 'suffix' => ''],
                ['label' => 'Başarılı gözlem', 'value' => $summary['successes'], 'suffix' => ''],
                ['label' => 'Başarısız gözlem', 'value' => $summary['failures'], 'suffix' => ''],
                ['label' => 'Ortalama güven', 'value' => $summary['confidence'], 'suffix' => '%'],
            ] as $metric)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
                    <p class="text-sm text-slate-400">{{ $metric['label'] }}</p>
                    <strong class="mt-2 block text-3xl">{{ number_format($metric['value'], $metric['suffix'] ? 1 : 0, ',', '.') }}{{ $metric['suffix'] }}</strong>
                </article>
            @endforeach
        </div>

        <form method="GET" action="{{ route('learned-routes.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-5 md:grid-cols-2 xl:grid-cols-5">
            <label class="xl:col-span-2">
                <span class="mb-1 block text-xs font-semibold text-slate-400">Host, yol veya amaç</span>
                <input name="q" value="{{ request('q') }}" maxlength="100" placeholder="news.example.com veya /wp-json/..." class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm outline-none focus:border-cyan-400">
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Metot</span>
                <select name="method" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    @foreach ($methods as $method)<option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ $method->value }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Durum</span>
                <select name="enabled" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    <option value="1" @selected(request('enabled') === '1')>Etkin</option>
                    <option value="0" @selected(request('enabled') === '0')>Devre dışı</option>
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Ajans</span>
                <select name="agency_id" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    @foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected((string) request('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>@endforeach
                </select>
            </label>
            <div class="flex gap-2 xl:col-start-5">
                <button class="flex-1 rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-cyan-300">Filtrele</button>
                <a href="{{ route('learned-routes.index') }}" class="rounded-xl border border-white/10 px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5">Sıfırla</a>
            </div>
        </form>

        <div class="grid gap-4">
            @forelse ($learnedRoutes as $route)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-cyan-400/15 px-2.5 py-1 font-mono text-xs font-black text-cyan-300">{{ $route->method->value }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $route->is_enabled ? 'bg-emerald-400/15 text-emerald-300' : 'bg-slate-400/15 text-slate-400' }}">{{ $route->is_enabled ? 'Etkin' : 'Devre dışı' }}</span>
                                <span class="text-xs text-slate-500">{{ $route->agency->name }}</span>
                            </div>
                            <h2 class="mt-3 break-all font-mono text-base font-bold text-white">{{ $route->host }}{{ $route->path_pattern }}</h2>
                            <p class="mt-2 text-sm text-slate-400">{{ $route->purpose ?? 'Amaç henüz tanımlanmadı' }}@if ($route->publishingTarget) · {{ $route->publishingTarget->name }}@endif</p>
                        </div>
                        <form method="POST" action="{{ route('learned-routes.status', $route) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_enabled" value="{{ $route->is_enabled ? 0 : 1 }}">
                            <button class="w-full rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/5 lg:w-auto">{{ $route->is_enabled ? 'Devre dışı bırak' : 'Etkinleştir' }}</button>
                        </form>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div><span class="block text-xs text-slate-500">Güven</span><strong class="{{ $route->confidence >= 80 ? 'text-emerald-300' : ($route->confidence >= 50 ? 'text-amber-300' : 'text-rose-300') }}">%{{ number_format($route->confidence, 1, ',', '.') }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Başarılı</span><strong>{{ number_format($route->successful_count, 0, ',', '.') }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Başarısız</span><strong>{{ number_format($route->failed_count, 0, ',', '.') }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Son HTTP</span><strong>{{ $route->last_status_code ?? 'Bağlantı yok' }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Son gözlem</span><strong class="text-sm">{{ $route->last_observed_at->diffForHumans() }}</strong></div>
                    </div>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-800"><div class="h-full rounded-full bg-gradient-to-r from-rose-400 via-amber-400 to-emerald-400" style="width: {{ $route->confidence }}%"></div></div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/15 px-5 py-14 text-center text-slate-400">Henüz öğrenilmiş bir entegrasyon rotası yok. Yayın çağrıları yapıldıkça bu ekran otomatik dolacak.</div>
            @endforelse
        </div>

        {{ $learnedRoutes->links() }}
    </section>
</x-layouts.app>
