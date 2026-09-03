<x-layouts.app title="Çeviri Çalışma Alanı">
<section class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('translations.index') }}" class="text-fuchsia-300">← Çeviri Merkezi</a>
    <header class="flex flex-col justify-between gap-4 sm:flex-row"><div><p class="text-sm text-fuchsia-300">{{ strtoupper($translation->source_locale) }} → {{ strtoupper($translation->target_locale) }}</p><h1 class="mt-2 text-3xl font-black">{{ $translation->article->title }}</h1><p class="mt-2 text-slate-500">{{ $translation->status->label() }}</p></div>@if ($translation->isSourceStale())<form method="POST" action="{{ route('translations.refresh', $translation) }}">@csrf<button class="rounded-xl bg-amber-300 px-5 py-3 font-black text-slate-950">Güncel kaynaktan yenile</button></form>@endif</header>
    @if ($translation->isSourceStale())<div class="rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-amber-100">Kaynak haber bu taslaktan sonra değişti. Çeviri onaylanamaz; önce taslağı yenileyin.</div>@endif
    <form method="POST" action="{{ route('translations.update', $translation) }}" class="space-y-5 rounded-2xl border border-white/10 bg-white/[.03] p-6">
        @csrf @method('PUT')
        <label class="block">Çevrilmiş başlık<input name="title" value="{{ old('title', $translation->title) }}" required maxlength="255" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
        <label class="block">Çevrilmiş özet<textarea name="summary" rows="4" maxlength="3000" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">{{ old('summary', $translation->summary) }}</textarea></label>
        <label class="block">Çevrilmiş metin<textarea name="body" rows="20" required minlength="50" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">{{ old('body', $translation->body) }}</textarea></label>
        <label class="block">Terim sözlüğü <small class="text-slate-500">Her satır: kaynak=hedef</small><textarea name="glossary" rows="5" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($translation->glossary ?? [] as $source=>$target){{ $source }}={{ $target }}
@endforeach</textarea></label>
        <label class="block">Durum<select name="status" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $translation->status->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        <button class="rounded-xl bg-fuchsia-300 px-6 py-3 font-black text-slate-950">Çeviriyi kaydet</button>
    </form>
</section>
</x-layouts.app>
