<x-layouts.app title="Haber Merkezi">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-bold tracking-[.18em] text-cyan-300">EDİTORYAL MASA</p>
                <h1 class="mt-3 text-4xl font-black">Haber Merkezi</h1>
                <p class="mt-2 text-slate-400">Taslakları, onay kuyruğunu, yayınları ve hatalı içerikleri yönetin.</p>
            </div>
            @can('create', App\Models\Article::class)
                <div class="flex flex-col gap-2 sm:flex-row">
                    @if(auth()->user()->isSystemAdministrator() || auth()->user()->isAgencyOwner())
                        <a href="{{ route('articles.generate-topic-form') }}" class="rounded-xl bg-cyan-300 px-5 py-3 text-center font-bold text-slate-950 hover:bg-cyan-200">AI ile haber üret</a>
                    @endif
                    <a href="{{ route('articles.create') }}" class="rounded-xl border border-cyan-300/30 px-5 py-3 text-center font-bold text-cyan-200 hover:bg-cyan-300/10">+ Manuel haber</a>
                </div>
            @endcan
        </header>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($statuses as $status)
                <a href="{{ route('articles.index', ['status' => $status->value]) }}" class="rounded-xl border p-4 transition {{ request('status') === $status->value ? 'border-cyan-300/50 bg-cyan-300/10' : 'border-white/10 bg-white/[.04] hover:border-white/20' }}">
                    <span class="text-sm text-slate-400">{{ $status->label() }}</span>
                    <strong class="mt-2 block text-2xl">{{ $statusCounts[$status->value] }}</strong>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('articles.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 md:grid-cols-[1fr_220px_220px_auto]">
            <input name="q" value="{{ request('q') }}" placeholder="Başlık, özet veya kaynak ara" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
            <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300"><option value="">Tüm durumlar</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            <select name="trust" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300"><option value="">Tüm güven durumları</option>@foreach ($trustStatuses as $trustStatus)<option value="{{ $trustStatus->value }}" @selected(request('trust') === $trustStatus->value)>{{ $trustStatus->label() }}</option>@endforeach</select>
            <button class="rounded-xl border border-cyan-300/30 px-5 py-3 font-bold text-cyan-200 hover:bg-cyan-300/10">Filtrele</button>
        </form>

        <form method="POST" action="{{ route('articles.bulk-action') }}">
            @csrf
            @method('PATCH')
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="text-sm text-slate-400">Seçilen haberleri:</span>
                <button name="action" value="draft" class="rounded-lg border border-slate-400/20 px-3 py-2 text-sm font-bold text-slate-200">Taslağa al</button>
                <button name="action" value="pending_approval" class="rounded-lg border border-amber-400/20 px-3 py-2 text-sm font-bold text-amber-300">Onaya gönder</button>
            </div>
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[.04]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b border-white/10 bg-white/[.03] text-xs uppercase tracking-wider text-slate-400">
                            <tr><th class="w-12 px-5 py-4">Seç</th><th class="px-5 py-4">Haber</th><th class="px-5 py-4">Ajans / Kaynak</th><th class="px-5 py-4">Durum</th><th class="px-5 py-4">Güncelleme</th><th class="px-5 py-4 text-right">İşlem</th></tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @forelse ($articles as $article)
                                <tr class="align-top">
                                    <td class="px-5 py-4"><input type="checkbox" name="items[]" value="{{ $article->id }}" class="h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300" aria-label="{{ $article->title }} seç"></td>
                                    <td class="max-w-xl px-5 py-4"><a href="{{ route('articles.show', $article) }}" class="font-bold hover:text-cyan-300">{{ $article->title }}</a><p class="mt-1 line-clamp-2 text-sm text-slate-400">{{ $article->summary ?: Str::limit($article->body, 130) }}</p></td>
                                    <td class="px-5 py-4 text-sm"><strong class="block">{{ $article->agency->name }}</strong><span class="text-slate-500">{{ $article->source_name ?: 'Manuel içerik' }}</span></td>
                                    <td class="px-5 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ match($article->status) { App\ArticleStatus::Published => 'bg-emerald-400/10 text-emerald-300', App\ArticleStatus::PendingApproval => 'bg-amber-400/10 text-amber-300', App\ArticleStatus::Failed => 'bg-rose-400/10 text-rose-300', default => 'bg-slate-400/10 text-slate-300' } }}">{{ $article->status->label() }}</span><span class="mt-2 block text-xs text-slate-500">{{ $article->source_trust_status->label() }}</span></td>
                                    <td class="px-5 py-4 text-sm text-slate-400">{{ $article->updated_at->diffForHumans() }}<span class="mt-1 block text-xs">{{ $article->author?->name ?? 'Otomasyon' }}</span></td>
                                    <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="{{ route('articles.show', $article) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm hover:bg-white/5">Görüntüle</a>@can('update', $article)<a href="{{ route('articles.edit', $article) }}" class="rounded-lg border border-cyan-400/20 px-3 py-2 text-sm text-cyan-300 hover:bg-cyan-400/10">Düzenle</a>@endcan</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-14 text-center text-slate-400">Bu filtrelerle eşleşen haber bulunamadı.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($articles->hasPages())<div class="border-t border-white/10 px-5 py-4">{{ $articles->links() }}</div>@endif
            </div>
        </form>
    </section>
</x-layouts.app>