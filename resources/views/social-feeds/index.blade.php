<x-layouts.app title="Sosyal Akış Alımı">
<section class="space-y-8">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-bold tracking-[.18em] text-violet-300">YAPILANDIRILMIŞ VERİ GİRİŞİ</p><h1 class="mt-3 text-4xl font-black">Sosyal Akış Alımı</h1><p class="mt-2 text-slate-400">Yerel JSON akışlarını alan eşleme, tekrar kayıt koruması ve sosyal dinleme analiziyle içeri alın.</p></div>
        <a href="{{ route('social-listening.index') }}" class="rounded-xl border border-sky-400/20 px-4 py-3 text-sky-300">Sosyal Dinlemeye dön</a>
    </header>

    <form method="POST" action="{{ route('social-feed-sources.store') }}" class="grid gap-4 rounded-2xl border border-white/10 bg-white/[.03] p-6 sm:grid-cols-2 xl:grid-cols-5">
        @csrf
        <label>Dinleme kuralı<select name="social_listening_watch_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($watches as $watch)<option value="{{ $watch->id }}">{{ $watch->name }}</option>@endforeach</select></label>
        <label>Kaynak adı<input name="name" required maxlength="120" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
        <label>Platform<select name="platform" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($platforms as $platform)<option value="{{ $platform }}">{{ strtoupper($platform) }}</option>@endforeach</select></label>
        <label>Gelecek uç nokta<input type="url" name="endpoint_url" placeholder="https://..." class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
        <div class="flex items-end gap-3"><label class="pb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktif</label><button class="ml-auto rounded-xl bg-violet-300 px-4 py-3 font-black text-slate-950">Kaynak ekle</button></div>
    </form>

    <div class="grid gap-5 xl:grid-cols-2">
        @forelse ($sources as $source)
            <article class="space-y-5 rounded-2xl border border-violet-400/20 bg-violet-400/[.03] p-6">
                <div class="flex justify-between gap-4"><div><p class="text-xs font-bold text-violet-300">{{ strtoupper($source->platform) }} · {{ $source->watch->name }}</p><h2 class="mt-1 text-xl font-black">{{ $source->name }}</h2></div><span class="text-sm {{ $source->is_active ? 'text-emerald-300' : 'text-slate-500' }}">{{ $source->is_active ? 'Aktif' : 'Pasif' }}</span></div>
                <form method="POST" action="{{ route('social-feed-imports.store', $source) }}" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-semibold">JSON listesi<textarea name="payload" rows="10" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 font-mono text-xs" placeholder='[{"id":"x-1","text":"ASYA hakkında paylaşım","author":"@hesap","url":"https://example.com/1","published_at":"{{ now()->toIso8601String() }}","engagement":42}]'>{{ old('payload') }}</textarea></label>
                    <p class="text-xs text-slate-500">Alanlar: id, text, author, url, published_at, engagement. En fazla 50 kayıt.</p>
                    <button class="rounded-xl bg-violet-300 px-4 py-2.5 font-black text-slate-950">Akışı içeri al</button>
                </form>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400 xl:col-span-2">Önce bir sosyal dinleme kuralı ve akış kaynağı oluşturun.</div>
        @endforelse
    </div>

    <section class="space-y-4"><h2 class="text-2xl font-black">Son alım çalışmaları</h2><div class="overflow-x-auto rounded-2xl border border-white/10"><table class="min-w-full text-left text-sm"><thead class="bg-white/[.04] text-slate-400"><tr><th class="p-4">Kaynak</th><th class="p-4">Durum</th><th class="p-4">Alınan</th><th class="p-4">Yeni</th><th class="p-4">Atlanan</th><th class="p-4">Hatalı</th></tr></thead><tbody>@forelse ($imports as $import)<tr class="border-t border-white/10"><td class="p-4">{{ $import->source->name }}</td><td class="p-4">{{ $import->status->label() }}</td><td class="p-4">{{ $import->received_count }}</td><td class="p-4 text-emerald-300">{{ $import->imported_count }}</td><td class="p-4 text-amber-300">{{ $import->skipped_count }}</td><td class="p-4 text-rose-300">{{ $import->failed_count }}</td></tr>@empty<tr><td colspan="6" class="p-10 text-center text-slate-500">Henüz alım çalışması yok.</td></tr>@endforelse</tbody></table></div></section>
</section>
</x-layouts.app>
