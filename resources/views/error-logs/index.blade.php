<x-layouts.app title="Hata Kayıtları">
    <section class="space-y-7">
        <header>
            <p class="text-sm font-bold tracking-[.18em] text-rose-300">SİSTEM SAĞLIĞI</p>
            <h1 class="mt-3 text-4xl font-black">Hata Kayıtları</h1>
            <p class="mt-2 max-w-3xl text-slate-400">Tekrarlayan uygulama hatalarını, önem derecelerini ve çözüm sürecini güvenli biçimde takip edin.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Benzersiz hata', 'value' => $summary['total'], 'color' => 'text-white'],
                ['label' => 'Açık kayıt', 'value' => $summary['open'], 'color' => 'text-amber-300'],
                ['label' => 'Kritik kayıt', 'value' => $summary['critical'], 'color' => 'text-rose-300'],
                ['label' => 'Toplam tekrar', 'value' => $summary['occurrences'], 'color' => 'text-cyan-300'],
            ] as $metric)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
                    <p class="text-sm text-slate-400">{{ $metric['label'] }}</p>
                    <strong class="mt-2 block text-3xl {{ $metric['color'] }}">{{ number_format($metric['value'], 0, ',', '.') }}</strong>
                </article>
            @endforeach
        </div>

        <form method="GET" action="{{ route('error-logs.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-5 md:grid-cols-2 xl:grid-cols-6">
            <label class="xl:col-span-2">
                <span class="mb-1 block text-xs font-semibold text-slate-400">Arama</span>
                <input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Mesaj, sınıf veya yol" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm outline-none focus:border-cyan-400">
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Durum</span>
                <select name="status" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Önem</span>
                <select name="severity" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    @foreach ($severities as $severity)<option value="{{ $severity->value }}" @selected(request('severity') === $severity->value)>{{ $severity->label() }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Ajans</span>
                <select name="agency_id" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                    <option value="">Tümü</option>
                    @foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected((string) request('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>@endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="flex-1 rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-cyan-300">Filtrele</button>
                <a href="{{ route('error-logs.index') }}" class="rounded-xl border border-white/10 px-3 py-2.5 text-sm text-slate-300 hover:bg-white/5">Sıfırla</a>
            </div>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Başlangıç</span>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
            </label>
            <label>
                <span class="mb-1 block text-xs font-semibold text-slate-400">Bitiş</span>
                <input type="date" name="to" value="{{ request('to') }}" max="{{ now()->toDateString() }}" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
            </label>
        </form>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[.03]">
            <div class="hidden grid-cols-[110px_1fr_110px_110px_150px] gap-4 border-b border-white/10 px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 lg:grid">
                <span>Önem</span><span>Hata</span><span>Durum</span><span>Tekrar</span><span>Son görülme</span>
            </div>
            <div class="divide-y divide-white/10">
                @forelse ($errorLogs as $errorLog)
                    <a href="{{ route('error-logs.show', $errorLog) }}" class="grid gap-3 px-5 py-4 transition hover:bg-white/[.04] lg:grid-cols-[110px_1fr_110px_110px_150px] lg:items-center lg:gap-4">
                        <span class="w-fit rounded-full px-2.5 py-1 text-xs font-bold {{ $errorLog->severity === App\ErrorSeverity::Critical ? 'bg-rose-400/15 text-rose-300' : ($errorLog->severity === App\ErrorSeverity::Warning ? 'bg-amber-400/15 text-amber-300' : 'bg-orange-400/15 text-orange-300') }}">{{ $errorLog->severity->label() }}</span>
                        <span class="min-w-0">
                            <strong class="block truncate text-sm text-white">{{ class_basename($errorLog->exception_class) }}</strong>
                            <small class="mt-1 block truncate text-slate-400">{{ $errorLog->message }}</small>
                            <small class="mt-1 block text-slate-500">{{ $errorLog->agency?->name ?? 'Sistem geneli' }} · {{ $errorLog->path ?? 'Arka plan işlemi' }}</small>
                        </span>
                        <span class="w-fit rounded-full px-2.5 py-1 text-xs {{ $errorLog->status === App\ErrorLogStatus::Open ? 'bg-amber-400/15 text-amber-300' : 'bg-emerald-400/15 text-emerald-300' }}">{{ $errorLog->status->label() }}</span>
                        <span><strong class="text-lg">{{ number_format($errorLog->occurrences, 0, ',', '.') }}</strong><small class="ml-1 text-slate-500">kez</small></span>
                        <time class="text-sm text-slate-400" datetime="{{ $errorLog->last_seen_at->toIso8601String() }}">{{ $errorLog->last_seen_at->diffForHumans() }}</time>
                    </a>
                @empty
                    <div class="px-5 py-14 text-center text-slate-400">Bu filtrelerle eşleşen hata kaydı yok.</div>
                @endforelse
            </div>
        </div>

        {{ $errorLogs->links() }}
    </section>
</x-layouts.app>
