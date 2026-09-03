<x-layouts.app title="Çeviri Merkezi">
<section class="space-y-8">
    <header><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">ÇOK DİLLİ EDİTORYAL AKIŞ</p><h1 class="mt-3 text-4xl font-black">Çeviri Merkezi</h1><p class="mt-2 text-slate-400">Türkçe haberlerden hedef dil taslağı açın, terim sözlüğünü koruyun ve insan incelemesiyle onaylayın.</p></header>
    <div class="rounded-xl border border-fuchsia-400/20 bg-fuchsia-400/[.05] p-4 text-sm text-fuchsia-100">Yerel Faz 1, kaynak metni hedef dil çalışma alanına güvenli bir taslak olarak taşır; otomatik çeviri iddiasında bulunmaz. Onay öncesi yetkin insan çevirisi ve olgu doğrulaması zorunludur.</div>
    <form method="POST" action="{{ route('translations.store') }}" class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/[.03] p-6 sm:flex-row sm:items-end">
        @csrf
        <label class="flex-1">Kaynak haber<select name="article_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($articles as $article)<option value="{{ $article->id }}">{{ $article->title }}</option>@endforeach</select></label>
        <label>Hedef dil<select name="target_locale" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($locales as $locale=>$label)<option value="{{ $locale }}">{{ $label }}</option>@endforeach</select></label>
        <button class="rounded-xl bg-fuchsia-300 px-5 py-3 font-black text-slate-950">Taslak aç</button>
    </form>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($translations as $translation)
            <article class="rounded-2xl border {{ $translation->isSourceStale() ? 'border-amber-400/30 bg-amber-400/[.04]' : 'border-white/10 bg-white/[.03]' }} p-5">
                <div class="flex justify-between gap-4"><div><p class="text-xs font-bold text-fuchsia-300">{{ strtoupper($translation->source_locale) }} → {{ strtoupper($translation->target_locale) }}</p><h2 class="mt-2 text-xl font-black">{{ $translation->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $translation->article->title }}</p></div><div class="text-right"><span class="text-sm">{{ $translation->status->label() }}</span>@if ($translation->isSourceStale())<small class="mt-1 block text-amber-300">Kaynak değişti</small>@endif</div></div>
                <a href="{{ route('translations.show', $translation) }}" class="mt-5 inline-block rounded-lg border border-fuchsia-400/20 px-3 py-2 text-sm text-fuchsia-300">Çalışma alanını aç</a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400 lg:col-span-2">Henüz çeviri taslağı yok.</div>
        @endforelse
    </div>
    {{ $translations->links() }}
</section>
</x-layouts.app>
