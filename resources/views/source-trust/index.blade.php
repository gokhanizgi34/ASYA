<x-layouts.app title="Haber Kaynağı Girişi">
    <section class="space-y-8">
        <header>
            <p class="text-sm font-bold tracking-[.18em] text-amber-300">OTOMATİK HABER ALIMI</p>
            <h1 class="mt-3 text-4xl font-black">Haber kaynağı bağlantıları</h1>
            <p class="mt-2 text-slate-400">Bağlantıyı ekleyin; kaynak 100 güven puanıyla açılır ve Akıllı Alım otomatik başlar.</p>
        </header>

        <form method="POST" action="{{ route('source-trust.sources.store') }}" class="grid gap-4 rounded-2xl border border-amber-400/20 bg-amber-400/[.03] p-6 sm:grid-cols-2 xl:grid-cols-6">
            @csrf
            <label>
                Ajans
                <select name="agency_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" @selected(old('agency_id') == $agency->id)>{{ $agency->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Kaynak adı
                <input name="name" value="{{ old('name') }}" required maxlength="160" placeholder="Örn. Pendik Belediyesi" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
            </label>
            <label class="xl:col-span-2">
                Haber sitesi veya akış bağlantısı
                <input type="url" name="feed_url" value="{{ old('feed_url') }}" required placeholder="https://site.com/haberler" class="mt-1 w-full rounded-xl border border-cyan-400/30 bg-slate-900 px-3 py-2.5">
            </label>
            <input type="hidden" name="feed_format" value="auto">
            <input type="hidden" name="allow_insecure_tls" value="0">
            <label class="flex items-end gap-2 pb-3 text-sm"><input type="checkbox" name="allow_insecure_tls" value="1" @checked(old('allow_insecure_tls'))> Sertifika doğrulaması olmadan al</label>
            <label>
                Tür
                <select name="source_type" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
                    @foreach (['news_site' => 'Haber sitesi', 'official' => 'Resmî kurum', 'agency' => 'Ajans', 'expert' => 'Uzman', 'social' => 'Sosyal hesap', 'other' => 'Diğer'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_type', 'news_site') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Günlük haber kotası
                <select name="daily_item_limit" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
                    @foreach ([1, 3, 5, 10, 15, 20, 30, 50, 100] as $limit)
                        <option value="{{ $limit }}" @selected((int) old('daily_item_limit', 10) === $limit)>{{ $limit }} haber/gün</option>
                    @endforeach
                </select>
            </label>            <div class="flex items-end gap-3">
                <input type="hidden" name="is_active" value="0">
                <label class="pb-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Aktif</label>
                <button class="ml-auto rounded-xl bg-amber-300 px-4 py-3 font-black text-slate-950">Ekle ve başlat</button>
            </div>
            <label class="sm:col-span-2 xl:col-span-6">
                Not
                <input name="notes" value="{{ old('notes') }}" maxlength="5000" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
            </label>
        </form>

        <div class="grid gap-5 xl:grid-cols-2">
            @forelse ($sources as $source)
                <article class="space-y-5 rounded-2xl border border-white/10 bg-white/[.03] p-6">
                    <div class="flex justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-xl font-black">{{ $source->name }}</h2>
                            <p class="text-sm text-slate-500">{{ $source->domain }} · {{ $source->source_type }}</p>
                            <p class="mt-1 break-all text-xs text-slate-500">{{ $source->feed_url }}</p>
                        </div>
                        <div class="text-right">
                            <strong class="text-emerald-300">{{ number_format($source->latest_score ?? 100, 0) }}</strong>
                            <small class="block text-slate-500">{{ $source->latest_band?->label() ?? 'Yüksek güven' }}</small>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @can('update', $source)
                            <details class="group w-full rounded-xl border border-cyan-400/20 bg-cyan-400/[.03]">
                                <summary class="cursor-pointer list-none px-4 py-3 font-bold text-cyan-300 marker:hidden">Kaynağı güncelle</summary>
                                <form method="POST" action="{{ route('source-trust.sources.update', $source) }}" class="grid gap-3 border-t border-cyan-400/10 p-4 sm:grid-cols-2">
                                    @csrf
                                    @method('PUT')
                                    <label class="text-sm font-bold text-slate-300">
                                        Kaynak adı
                                        <input name="name" value="{{ $source->name }}" required maxlength="160" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2.5 font-normal">
                                    </label>
                                    <label class="text-sm font-bold text-slate-300">
                                        Tür
                                        <select name="source_type" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2.5 font-normal">
                                            @foreach (['news_site' => 'Haber sitesi', 'official' => 'Resmî kurum', 'agency' => 'Ajans', 'expert' => 'Uzman', 'social' => 'Sosyal hesap', 'other' => 'Diğer'] as $value => $label)
                                                <option value="{{ $value }}" @selected($source->source_type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-sm font-bold text-slate-300 sm:col-span-2">
                                        Haber sitesi veya akış bağlantısı
                                        <input type="url" name="feed_url" value="{{ $source->feed_url }}" required maxlength="2048" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2.5 font-normal">
                                    </label>
                                    <input type="hidden" name="allow_insecure_tls" value="0">
                                    <label class="text-sm font-bold text-slate-300 sm:col-span-2"><input type="checkbox" name="allow_insecure_tls" value="1" @checked($source->allow_insecure_tls)> Sertifika doğrulaması olmadan al</label>
                                    <label class="text-sm font-bold text-slate-300 sm:col-span-2">
                                        Not
                                        <input name="notes" value="{{ $source->notes }}" maxlength="5000" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2.5 font-normal">
                                    </label>
                                    <label class="text-sm font-bold text-slate-300">
                                        Günlük haber kotası
                                        <select name="daily_item_limit" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-3 py-2.5 font-normal">
                                            @foreach ([1, 3, 5, 10, 15, 20, 30, 50, 100] as $limit)
                                                <option value="{{ $limit }}" @selected((int) $source->daily_item_limit === $limit)>{{ $limit }} haber/gün</option>
                                            @endforeach
                                        </select>
                                    </label>                                    <input type="hidden" name="feed_format" value="auto">
                                    <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
                                        <div>
                                            <input type="hidden" name="is_active" value="0">
                                            <label class="text-sm font-bold text-slate-300"><input type="checkbox" name="is_active" value="1" @checked($source->is_active)> Kaynak aktif</label>
                                        </div>
                                        <button class="rounded-lg bg-cyan-300 px-4 py-2.5 font-black text-slate-950">Güncelle</button>
                                    </div>
                                </form>
                            </details>
                        @endcan

                        @can('delete', $source)
                            <form method="POST" action="{{ route('source-trust.sources.destroy', $source) }}" onsubmit="return confirm('Bu haber kaynağı ve güven geçmişi kalıcı olarak silinsin mi?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-rose-400/30 px-4 py-2.5 font-bold text-rose-300 hover:bg-rose-400/10">Kaynağı sil</button>
                            </form>
                        @endcan
                    </div>
                    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-slate-950/60 p-3">
                        <form method="POST" action="{{ route('source-trust.sources.import', $source) }}">
                            @csrf
                            <button class="rounded-lg bg-cyan-300 px-4 py-2 font-black text-slate-950">Şimdi haberleri al</button>
                        </form>
                        <span class="text-xs text-slate-500">
                            Son alım: {{ $source->last_fetched_at?->format('d.m.Y H:i') ?? 'Kuyrukta' }}
                            · HTTP {{ $source->last_status_code ?? '—' }}
                            · {{ $source->last_item_count }} kayıt
                            · Günlük kota {{ $source->today_raw_news_count }}/{{ $source->daily_item_limit }}
                            · {{ ['rss_atom_xml' => 'RSS/Atom/XML', 'wordpress_json_api' => 'WordPress API', 'json_api' => 'JSON API', 'html_dom_crawl' => 'HTML/DOM', 'visual_ai_ocr' => 'Görsel/AI'][$source->last_ingestion_method] ?? 'Otomatik' }}
                        </span>
                    </div>

                    @if ($source->last_fetch_error)
                        <p class="rounded-lg bg-rose-400/10 p-3 text-sm text-rose-300">{{ $source->last_fetch_error }}</p>
                    @endif

                    <form method="POST" action="{{ route('source-trust.assessments.store', $source) }}" class="flex flex-wrap items-end gap-3 border-t border-white/10 pt-4">
                        @csrf
                        <label class="text-sm font-bold text-amber-300">
                            Güven puanı
                            <select name="trust_score" class="mt-1 block rounded-lg border border-amber-400/20 bg-slate-900 px-4 py-2.5 text-slate-100">
                                @foreach ([10, 30, 50, 70, 90, 100] as $score)
                                    <option value="{{ $score }}" @selected((int) ($source->latest_score ?? 100) === $score)>{{ $score }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="rounded-xl border border-amber-400/30 px-4 py-2.5 font-bold text-amber-300">Puanı güncelle</button>
                    </form>

                    @if ($source->assessments->isNotEmpty())
                        <div class="text-xs text-slate-500">
                            Son değişiklikler:
                            @foreach ($source->assessments as $assessment)
                                <span class="ml-2">{{ $assessment->assessed_at->format('d.m.Y') }} · {{ number_format($assessment->weighted_score, 0) }}</span>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400 xl:col-span-2">Henüz kaynak yok. İlk bağlantıyı eklediğinizde otomatik haber alımı başlayacak.</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>