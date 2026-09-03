<x-layouts.app title="Hata Ayrıntısı">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
            <div class="min-w-0">
                <a href="{{ route('error-logs.index') }}" class="text-sm font-semibold text-cyan-300 hover:text-cyan-200">← Hata kayıtları</a>
                <p class="mt-5 text-sm font-bold tracking-[.18em] text-rose-300">{{ $errorLog->severity->label() }} · {{ $errorLog->status->label() }}</p>
                <h1 class="mt-2 break-words text-3xl font-black">{{ class_basename($errorLog->exception_class) }}</h1>
                <p class="mt-3 break-words text-slate-300">{{ $errorLog->message }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[.04] px-5 py-4 text-right">
                <span class="block text-xs uppercase tracking-wider text-slate-500">Tekrar</span>
                <strong class="text-3xl">{{ number_format($errorLog->occurrences, 0, ',', '.') }}</strong>
            </div>
        </header>

        <div class="grid gap-5 lg:grid-cols-3">
            <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5 lg:col-span-2">
                <h2 class="text-lg font-bold">Teknik bağlam</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Ajans' => $errorLog->agency?->name ?? 'Sistem geneli',
                        'Kullanıcı' => $errorLog->user?->name ?? 'Arka plan / bilinmiyor',
                        'İstek' => trim(($errorLog->http_method ?? '').' /'.($errorLog->path ?? ''), ' /') ?: 'Arka plan işlemi',
                        'Rota' => $errorLog->route_name ?? '—',
                        'Dosya' => $errorLog->file.($errorLog->line ? ':'.$errorLog->line : ''),
                        'Parmak izi' => $errorLog->fingerprint,
                        'İlk görülme' => $errorLog->first_seen_at->format('d.m.Y H:i:s'),
                        'Son görülme' => $errorLog->last_seen_at->format('d.m.Y H:i:s'),
                    ] as $label => $value)
                        <div class="min-w-0 rounded-xl bg-slate-900/70 p-3">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 break-all text-sm text-slate-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <aside class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
                <h2 class="text-lg font-bold">İşlem</h2>
                @if ($errorLog->status === App\ErrorLogStatus::Open)
                    <div class="mt-4 space-y-4">
                        <form method="POST" action="{{ route('error-logs.status', $errorLog) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <input type="hidden" name="operation" value="resolve">
                            <textarea name="resolution_note" required maxlength="2000" rows="3" placeholder="Uygulanan çözümü yazın" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-sm outline-none focus:border-emerald-400"></textarea>
                            <button class="w-full rounded-xl bg-emerald-400 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-300">Çözüldü olarak işaretle</button>
                        </form>
                        <form method="POST" action="{{ route('error-logs.status', $errorLog) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <input type="hidden" name="operation" value="ignore">
                            <textarea name="resolution_note" required maxlength="2000" rows="2" placeholder="Yok sayma nedenini yazın" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-sm outline-none focus:border-amber-400"></textarea>
                            <button class="w-full rounded-xl border border-amber-400/30 px-4 py-2.5 text-sm font-bold text-amber-300 hover:bg-amber-400/10">Yok say</button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 rounded-xl bg-slate-900/70 p-4">
                        <p class="text-sm text-slate-300">{{ $errorLog->resolution_note }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $errorLog->resolvedBy?->name ?? 'Bilinmeyen kullanıcı' }} · {{ $errorLog->resolved_at?->format('d.m.Y H:i') }}</p>
                    </div>
                    <form method="POST" action="{{ route('error-logs.status', $errorLog) }}" class="mt-4">
                        @csrf @method('PATCH')
                        <input type="hidden" name="operation" value="reopen">
                        <button class="w-full rounded-xl border border-cyan-400/30 px-4 py-2.5 text-sm font-bold text-cyan-300 hover:bg-cyan-400/10">Yeniden aç</button>
                    </form>
                @endif
            </aside>
        </div>

        <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
            <h2 class="text-lg font-bold">Yığın izi</h2>
            <div class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-slate-400">
                @forelse (($errorLog->context['trace'] ?? []) as $index => $frame)
                    <div class="mb-2 min-w-[680px]"><span class="text-slate-600">#{{ $index }}</span> <span class="text-cyan-300">{{ $frame['class'] ?? '' }}{{ isset($frame['class']) ? '::' : '' }}{{ $frame['function'] ?? '' }}</span> <span>{{ $frame['file'] ?? '[internal]' }}{{ isset($frame['line']) ? ':'.$frame['line'] : '' }}</span></div>
                @empty
                    <p>Yığın izi bulunmuyor.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.app>
