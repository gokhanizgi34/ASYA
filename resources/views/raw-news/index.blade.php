<x-layouts.app title="Ham Haber Havuzu">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-bold tracking-[.18em] text-cyan-300">VERİ TAMPON BÖLGESİ</p>
                <h1 class="mt-3 text-4xl font-black">Ham Haber Havuzu</h1>
                <p class="mt-2 text-slate-400">Henüz yapay zekâ işleminden geçmemiş başlık, metin ve kaynak verilerini yönetin.</p>
            </div>
            @can('create', App\Models\RawNewsItem::class)
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('content-batches.create') }}" class="rounded-xl border border-violet-300/30 px-5 py-3 text-center font-bold text-violet-200 hover:bg-violet-300/10">AI haber üret</a>
                    <a href="{{ route('raw-news.create') }}" class="rounded-xl bg-cyan-300 px-5 py-3 text-center font-bold text-slate-950 hover:bg-cyan-200">+ Ham veri ekle</a>
                </div>
            @endcan
        </header>

        @can('create', App\Models\RawNewsItem::class)
            <form method="POST" action="{{ route('raw-news.all-action') }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-cyan-300/20 bg-cyan-300/[.05] p-4">
                @csrf
                @method('PATCH')
                <strong class="mr-auto text-sm text-cyan-100">Tüm havuz işlemleri</strong>
                <button name="action" value="retry_all" onclick="return confirm('Hatalı tüm haberler yeniden işleme alınsın mı?')" class="rounded-xl border border-amber-300/30 px-4 py-2.5 font-bold text-amber-200 hover:bg-amber-300/10">Tümünü yeniden dene</button>
                <button name="action" value="queue_all" onclick="return confirm('Bekleyen ve hatalı tüm haberler üretim kuyruğuna alınsın mı?')" class="rounded-xl bg-cyan-300 px-4 py-2.5 font-bold text-slate-950 hover:bg-cyan-200">Tümünü kuyruğa al</button>
            </form>
        @endcan

        <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($statuses as $status)
                <a href="{{ route('raw-news.index', ['status' => $status->value]) }}" class="rounded-xl border p-4 {{ request('status') === $status->value ? 'border-cyan-300/50 bg-cyan-300/10' : 'border-white/10 bg-white/[.04] hover:border-white/20' }}">
                    <span class="text-xs text-slate-400">{{ $status->label() }}</span>
                    <strong class="mt-2 block text-2xl">{{ $statusCounts[$status->value] }}</strong>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('raw-news.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 md:grid-cols-[1fr_220px_180px_auto]">
            <input name="q" value="{{ request('q') }}" placeholder="Başlık, metin veya kaynak ara" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
            <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                <option value="">Tüm durumlar</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="language" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                <option value="">Tüm diller</option>
                @foreach ($languages as $language)
                    <option value="{{ $language }}" @selected(request('language') === $language)>{{ strtoupper($language) }}</option>
                @endforeach
            </select>
            <button class="rounded-xl border border-cyan-300/30 px-5 py-3 font-bold text-cyan-200 hover:bg-cyan-300/10">Filtrele</button>
        </form>

        <form method="POST" action="{{ route('raw-news.bulk-action') }}">
            @csrf
            @method('PATCH')
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="mr-2 text-sm text-slate-400">Seçilenler:</span>
                <button name="action" value="queue" class="rounded-lg border border-cyan-400/20 px-3 py-2 text-sm font-bold text-cyan-300 hover:bg-cyan-400/10">Kuyruğa al</button>
                <button name="action" value="reject" class="rounded-lg border border-rose-400/20 px-3 py-2 text-sm font-bold text-rose-300 hover:bg-rose-400/10">Reddet</button>
                <button name="action" value="retry" class="rounded-lg border border-amber-400/20 px-3 py-2 text-sm font-bold text-amber-300 hover:bg-amber-400/10">Yeniden dene</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[.04]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b border-white/10 bg-white/[.03] text-xs uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="w-12 px-5 py-4"><span class="sr-only">Seç</span></th>
                                <th class="px-5 py-4">Ham haber</th>
                                <th class="px-5 py-4">Kaynak</th>
                                <th class="px-5 py-4">Durum</th>
                                <th class="px-5 py-4">Öncelik</th>
                                <th class="px-5 py-4 text-right">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($items as $item)
                                <tr class="align-top">
                                    <td class="px-5 py-4"><input type="checkbox" name="items[]" value="{{ $item->id }}" class="h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300" aria-label="{{ $item->original_title }} seç"></td>
                                    <td class="max-w-xl px-5 py-4"><a href="{{ route('raw-news.show', $item) }}" class="font-bold hover:text-cyan-300">{{ $item->original_title }}</a><p class="mt-1 line-clamp-2 text-sm text-slate-400">{{ Str::limit($item->original_body, 150) }}</p></td>
                                    <td class="px-5 py-4 text-sm"><strong class="block">{{ $item->source_name }}</strong><span class="text-slate-500">{{ $item->agency->name }} · {{ strtoupper($item->language) }}</span></td>
                                    <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ match ($item->status) { App\RawNewsStatus::Processed => 'bg-emerald-400/10 text-emerald-300', App\RawNewsStatus::Queued, App\RawNewsStatus::Processing => 'bg-cyan-400/10 text-cyan-300', App\RawNewsStatus::Failed => 'bg-rose-400/10 text-rose-300', App\RawNewsStatus::Rejected => 'bg-slate-400/10 text-slate-400', default => 'bg-amber-400/10 text-amber-300' } }}">{{ $item->status->label() }}</span></td>
                                    <td class="px-5 py-4"><strong>{{ $item->priority }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $item->discovered_at?->diffForHumans() }}</span></td>
                                    <td class="px-5 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('raw-news.show', $item) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm hover:bg-white/5">İncele</a>@can('create', App\Models\RawNewsItem::class) @if(in_array($item->status, [App\RawNewsStatus::Pending, App\RawNewsStatus::Failed], true))<form method="POST" action="{{ route('raw-news.production', $item) }}">@csrf<button class="rounded-lg bg-cyan-300 px-3 py-2 text-sm font-bold text-slate-950">Üretime gönder</button></form>@endif @endcan @can('update',$item)<a href="{{ route('raw-news.edit',$item) }}" class="rounded-lg border border-cyan-400/20 px-3 py-2 text-sm text-cyan-300">Düzenle</a>@endcan</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-14 text-center text-slate-400">Havuzda eşleşen veri bulunamadı.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="border-t border-white/10 px-5 py-4">{{ $items->links() }}</div>
                @endif
            </div>
        </form>
    </section>
</x-layouts.app>
