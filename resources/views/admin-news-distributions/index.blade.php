<x-layouts.app title="Toplu Haber Dağıtımı">
    <section class="space-y-8">
        <header>
            <p class="text-sm font-bold tracking-[.18em] text-cyan-300">SİSTEM YÖNETİCİSİ</p>
            <h1 class="mt-3 text-4xl font-black">Toplu Haber Dağıtımı</h1>
            <p class="mt-2 max-w-3xl text-slate-400">Haberi bir kez girin; seçtiğiniz ajansların tüm aktif WordPress sitelerine metni değiştirmeden, kategori, etiket ve SEO ayarlarıyla gönderin.</p>
        </header>

        <form method="POST" action="{{ route('admin-news-distributions.store') }}" enctype="multipart/form-data" class="space-y-6 rounded-2xl border border-cyan-300/20 bg-white/[.04] p-5 sm:p-7">
            @csrf

            <div class="grid gap-5 lg:grid-cols-2">
                <label class="space-y-2 lg:col-span-2">
                    <span class="text-sm font-bold text-slate-200">Başlık</span>
                    <input name="title" value="{{ old('title') }}" required maxlength="255" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                    @error('title')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </label>

                <label class="space-y-2 lg:col-span-2">
                    <span class="text-sm font-bold text-slate-200">Özet</span>
                    <textarea name="summary" required maxlength="2000" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 leading-7 outline-none focus:border-cyan-300">{{ old('summary') }}</textarea>
                    @error('summary')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </label>

                <label class="space-y-2 lg:col-span-2">
                    <span class="text-sm font-bold text-slate-200">Haber içeriği</span>
                    <textarea name="body" required rows="14" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 font-mono text-sm leading-7 outline-none focus:border-cyan-300" placeholder="Metin aynen yayımlanır. Ara başlıklar için ## Başlık yazabilirsiniz.">{{ old('body') }}</textarea>
                    <small class="block text-slate-500">Sistem metni yeniden yazmaz. WordPress için yalnızca okunabilir paragraf ve H2/H3/H4 biçimine dönüştürür.</small>
                    @error('body')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </label>

                <label class="space-y-2 lg:col-span-2">
                    <span class="text-sm font-bold text-slate-200">Görsel</span>
                    <input type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-dashed border-white/15 bg-slate-900 px-4 py-4 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-300 file:px-4 file:py-2 file:font-bold file:text-slate-950">
                    <small class="block text-slate-500">JPG, PNG veya WEBP; en fazla 15 MB.</small>
                    @error('image')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </label>
            </div>

            <fieldset class="space-y-4 rounded-2xl border border-white/10 bg-slate-950/60 p-5">
                <legend class="px-2 text-sm font-black text-cyan-200">Gönderilecek ajanslar</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach([['all', 'Tüm ajanslar', 'Kayıtlı tüm aktif ajanslar'], ['province', 'İle göre', 'Ajans kaydındaki il bilgisi'], ['selected', 'Tek tek seç', 'Belirlediğiniz ajanslar']] as [$value, $label, $description])
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-white/10 bg-slate-900 p-4 hover:border-cyan-300/40">
                            <input type="radio" name="selection_mode" value="{{ $value }}" required @checked(old('selection_mode', 'all') === $value) class="mt-1 h-5 w-5 text-cyan-300">
                            <span><strong class="block">{{ $label }}</strong><small class="text-slate-500">{{ $description }}</small></span>
                        </label>
                    @endforeach
                </div>

                <label class="block space-y-2">
                    <span class="text-sm font-bold text-slate-300">İl seçimi</span>
                    <select name="province" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                        <option value="">İl seçin</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province }}" @selected(old('province') === $province)>{{ $province }}</option>
                        @endforeach
                    </select>
                    @error('province')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </label>

                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold text-slate-300">Ajans seçimi</span>
                        <span class="text-xs text-slate-500">{{ $agencies->count() }} aktif ajans</span>
                    </div>
                    <div class="grid max-h-80 gap-2 overflow-y-auto rounded-xl border border-white/10 bg-slate-900 p-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($agencies as $agency)
                            <label class="flex gap-3 rounded-lg border border-white/5 p-3 hover:bg-white/5">
                                <input type="checkbox" name="agency_ids[]" value="{{ $agency->id }}" @checked(in_array($agency->id, array_map('intval', old('agency_ids', [])), true)) class="mt-1 h-5 w-5 rounded text-cyan-300">
                                <span class="min-w-0"><strong class="block truncate">{{ $agency->name }}</strong><small class="text-slate-500">{{ $agency->province ?: 'İl yok' }} · {{ $agency->publishing_targets_count }} aktif site</small></span>
                            </label>
                        @endforeach
                    </div>
                    @error('agency_ids')<small class="block text-rose-300">{{ $message }}</small>@enderror
                    @error('selection_mode')<small class="block text-rose-300">{{ $message }}</small>@enderror
                </div>
            </fieldset>

            <div class="flex justify-end">
                <button class="rounded-xl bg-cyan-300 px-7 py-3.5 font-black text-slate-950 hover:bg-cyan-200">Haberi seçili sitelere gönder</button>
            </div>
        </form>

        <section class="space-y-5">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <h2 class="text-2xl font-black">Yayın raporları</h2>
                    <p class="mt-1 text-sm text-slate-500">Tarih aralığına göre gönderilen siteleri ve oluşan haber bağlantılarını izleyin.</p>
                </div>
                <form method="GET" action="{{ route('admin-news-distributions.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="space-y-1"><span class="block text-xs text-slate-500">Başlangıç</span><input type="date" name="from" value="{{ request('from') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3"></label>
                    <label class="space-y-1"><span class="block text-xs text-slate-500">Bitiş</span><input type="date" name="to" value="{{ request('to') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3"></label>
                    <button class="rounded-xl border border-cyan-300/30 px-5 py-3 font-bold text-cyan-200">Filtrele</button>
                    <a href="{{ route('admin-news-distributions.export', request()->only(['from', 'to'])) }}" class="rounded-xl bg-emerald-300 px-5 py-3 text-center font-bold text-slate-950">Excel indir</a>
                </form>
            </div>

            <div class="space-y-4">
                @forelse($distributions as $distribution)
                    @php
                        $publishedCount = $distribution->items->filter(fn ($item) => $item->publication?->status === App\PublicationStatus::Published)->count();
                        $failedCount = $distribution->items->filter(fn ($item) => filled($item->failure_message) || $item->publication?->status === App\PublicationStatus::Failed)->count();
                    @endphp
                    <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <time class="text-xs font-bold text-cyan-300">{{ $distribution->created_at->format('d.m.Y H:i') }}</time>
                                <h3 class="mt-2 text-xl font-black">{{ $distribution->title }}</h3>
                                <p class="mt-2 text-sm text-slate-400">{{ $distribution->recipient_agency_count }} ajans · {{ $distribution->publication_count }} site · {{ $publishedCount }} yayımlandı @if($failedCount) · <span class="text-rose-300">{{ $failedCount }} hata</span>@endif</p>
                            </div>
                            <span class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-bold">{{ match($distribution->selection_mode) { 'all' => 'Tüm ajanslar', 'province' => $distribution->province, default => 'Seçili ajanslar' } }}</span>
                        </div>
                        <details class="mt-4">
                            <summary class="cursor-pointer font-bold text-cyan-200">Site sonuçlarını göster</summary>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Ajans</th><th class="px-3 py-2">Site</th><th class="px-3 py-2">Durum</th><th class="px-3 py-2">Haber bağlantısı</th></tr></thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach($distribution->items as $item)
                                            <tr><td class="px-3 py-3">{{ $item->agency->name }}</td><td class="px-3 py-3">{{ $item->publishingTarget?->name ?? 'Aktif hedef yok' }}</td><td class="px-3 py-3">{{ $item->publication?->status->label() ?? 'Gönderilemedi' }}</td><td class="max-w-md px-3 py-3">@if($item->publication?->remote_url)<a href="{{ $item->publication->remote_url }}" target="_blank" rel="noopener" class="break-all text-cyan-300 hover:underline">Haberi aç</a>@else<span class="text-slate-500">{{ $item->failure_message ?? $item->publication?->failure_message ?? 'Bağlantı bekleniyor' }}</span>@endif</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/15 p-12 text-center text-slate-400">Bu tarih aralığında toplu dağıtım bulunmuyor.</div>
                @endforelse
            </div>
            {{ $distributions->links() }}
        </section>
    </section>
</x-layouts.app>
