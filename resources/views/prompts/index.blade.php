<x-layouts.app title="AI & Prompt Yönetimi">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-cyan-300">AI KONTROL MASASI</p><h1 class="mt-3 text-4xl font-black">AI & Prompt Yönetimi</h1><p class="mt-2 text-slate-400">İçerik üretim talimatlarını kapsam, ton ve sürüm bilgisiyle yönetin.</p></div>@can('create', App\Models\AiPrompt::class)<a href="{{ route('prompts.create') }}" class="rounded-xl bg-cyan-300 px-5 py-3 text-center font-bold text-slate-950 hover:bg-cyan-200">+ Prompt oluştur</a>@endcan</header>

        <form method="GET" action="{{ route('prompts.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 md:grid-cols-[1fr_220px_180px_auto]">
            <input name="q" value="{{ request('q') }}" placeholder="Şablon veya sistem promptu ara" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
            <select name="category" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300"><option value="">Tüm kategoriler</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ str($category)->replace('_', ' ')->title() }}</option>@endforeach</select>
            <select name="active" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300"><option value="">Tüm durumlar</option><option value="1" @selected(request('active') === '1')>Aktif</option><option value="0" @selected(request('active') === '0')>Pasif</option></select>
            <button class="rounded-xl border border-cyan-300/30 px-5 py-3 font-bold text-cyan-200 hover:bg-cyan-300/10">Filtrele</button>
        </form>

        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($prompts as $prompt)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-black">{{ $prompt->name }}</h2><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $prompt->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-400/10 text-slate-400' }}">{{ $prompt->is_active ? 'Aktif' : 'Pasif' }}</span></div><p class="mt-1 text-sm text-slate-400">{{ $prompt->agency?->name ?? 'Global sistem şablonu' }}</p></div><span class="rounded-lg border border-white/10 px-3 py-1 text-xs text-slate-300">v{{ $prompt->version }}</span></div>
                <div class="mt-5 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"><div><span class="block text-slate-500">Kategori</span><strong>{{ str($prompt->category)->replace('_', ' ')->title() }}</strong></div><div><span class="block text-slate-500">Ton</span><strong>{{ $prompt->tone->label() }}</strong></div><div><span class="block text-slate-500">Dil</span><strong>{{ $prompt->language }}</strong></div><div><span class="block text-slate-500">Hedef</span><strong>{{ $prompt->target_length }} kelime</strong></div></div>
                <p class="mt-5 line-clamp-3 rounded-xl bg-slate-900/70 p-4 text-sm text-slate-400">{{ $prompt->system_prompt }}</p>
                <div class="mt-5 flex justify-end"><a href="{{ route('prompts.simulation', $prompt) }}" class="rounded-lg border border-violet-400/20 px-3 py-2 text-sm text-violet-300 hover:bg-violet-400/10">Simüle et</a></div>
                @can('update', $prompt)<div class="mt-2 flex justify-end gap-2"><a href="{{ route('prompts.edit', $prompt) }}" class="rounded-lg border border-cyan-400/20 px-3 py-2 text-sm text-cyan-300 hover:bg-cyan-400/10">Düzenle</a><form method="POST" action="{{ route('prompts.destroy', $prompt) }}" onsubmit="return confirm('Bu prompt şablonu silinsin mi?')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-400/20 px-3 py-2 text-sm text-rose-300 hover:bg-rose-400/10">Sil</button></form></div>@endcan
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/15 p-12 text-center text-slate-400 lg:col-span-2">Bu filtrelerle eşleşen prompt şablonu bulunamadı.</div>
            @endforelse
        </div>
        @if($prompts->hasPages())<div>{{ $prompts->links() }}</div>@endif
    </section>
</x-layouts.app>