<x-layouts.app title="Yayın Kaydı"><section class="mx-auto max-w-5xl space-y-7"><header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-amber-300">YAYIN #{{ $publication->id }}</p><h1 class="mt-3 text-3xl font-black">{{ $publication->article->title }}</h1><p class="mt-2 text-slate-400">{{ $publication->publishingTarget->name }} · {{ $publication->remote_status->label() }}</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('publications.index') }}" class="rounded-xl border border-white/10 px-5 py-3 text-center font-bold">Listeye dön</a>@can('update',$publication)<a href="{{ route('publications.edit',$publication) }}" class="rounded-xl border border-amber-400/20 px-5 py-3 font-bold text-amber-300">Düzenle</a><form method="POST" action="{{ route('publications.dispatch',$publication) }}">@csrf<button class="rounded-xl bg-cyan-300 px-5 py-3 font-bold text-slate-950">{{ $publication->status === App\PublicationStatus::Failed ? 'Yeniden dene' : 'Gönder' }}</button></form>@endcan @can('delete',$publication)<form method="POST" action="{{ route('publications.destroy',$publication) }}" onsubmit="return confirm('Yayın kaydı silinsin mi?')">@csrf @method('DELETE')<button class="rounded-xl border border-rose-400/20 px-5 py-3 font-bold text-rose-300">Sil</button></form>@endcan</div></header><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach([['Durum',$publication->status->label()],['Deneme',$publication->attempt_count],['Uzak yazı',$publication->remote_post_id ? '#'.$publication->remote_post_id : '—'],['Uzak medya',$publication->remote_media_id ? '#'.$publication->remote_media_id : '—']] as [$label,$value])<div class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><span class="text-sm text-slate-500">{{ $label }}</span><strong class="mt-2 block text-xl">{{ $value }}</strong></div>@endforeach</div>@if($publication->failure_message)<div class="rounded-2xl border border-rose-400/20 bg-rose-400/10 p-5"><h2 class="font-black text-rose-200">Yayın hatası</h2><p class="mt-2 break-words text-sm text-rose-100">{{ $publication->failure_message }}</p>@can('update',$publication) @if($publication->status === App\PublicationStatus::Failed)<form method="POST" action="{{ route('publications.dispatch', $publication) }}" class="mt-4">@csrf<button class="rounded-xl bg-rose-200 px-5 py-3 font-bold text-rose-950">Yeniden kuyruğa al</button></form>@endif @endcan</div>@endif
@if($publication->remote_url)
@php($googleIndexStatus = data_get($publication->response_meta, 'google_search_console'))
<article class="rounded-2xl border border-emerald-400/20 bg-emerald-400/[.04] p-5">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-xl font-black text-emerald-200">Google indeks takibi</h2>
            @if($googleIndexStatus)
                <p class="mt-2 text-sm text-slate-300">{{ data_get($googleIndexStatus, 'coverage_state', 'Durum Google tarafından henüz bildirilmedi.') }}</p>
            @else
                <p class="mt-2 text-sm text-slate-300">Yayın adresi oluşturuldu. Search Console bağlantısı aktifse denetim sonucu otomatik kaydedilir.</p>
            @endif
        </div>
        @if(data_get($googleIndexStatus, 'verdict'))
            <span class="w-fit rounded-full bg-emerald-300/10 px-3 py-1 text-sm font-bold text-emerald-200">{{ data_get($googleIndexStatus, 'verdict') }}</span>
        @endif
    </div>
    @if(data_get($googleIndexStatus, 'error'))
        <p class="mt-4 break-words rounded-xl bg-rose-400/10 p-3 text-sm text-rose-200">{{ data_get($googleIndexStatus, 'error') }}</p>
    @endif
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <div><dt class="text-slate-500">İndeks izni</dt><dd class="mt-1 font-bold">{{ data_get($googleIndexStatus, 'indexing_state', 'Bekleniyor') }}</dd></div>
        <div><dt class="text-slate-500">Sayfa alımı</dt><dd class="mt-1 font-bold">{{ data_get($googleIndexStatus, 'page_fetch_state', 'Bekleniyor') }}</dd></div>
        <div><dt class="text-slate-500">Son tarama</dt><dd class="mt-1 font-bold">{{ data_get($googleIndexStatus, 'last_crawl_time', 'Henüz yok') }}</dd></div>
    </dl>
</article>
@endif
<article class="rounded-2xl border border-cyan-400/20 bg-cyan-400/[.04] p-6">
    <h2 class="text-xl font-black text-cyan-200">Haber içeriği</h2>
    <p class="mt-2 text-sm font-bold text-slate-300">{{ data_get($publication->payload, 'title', $publication->article->title) }}</p>
    <div class="mt-5 whitespace-pre-line text-base leading-8 text-slate-200">{{ data_get($publication->payload, 'content', $publication->article->body) }}</div>
</article><div class="grid gap-5 lg:grid-cols-2"><article class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><h2 class="text-xl font-black">Dondurulan içerik</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Başlık</dt><dd class="font-bold">{{ data_get($publication->payload,'title') }}</dd></div><div><dt class="text-slate-500">Slug</dt><dd>{{ data_get($publication->payload,'slug') }}</dd></div><div><dt class="text-slate-500">Kategoriler / Etiketler</dt><dd>{{ implode(', ', data_get($publication->payload,'categories',[])) ?: '—' }} / {{ implode(', ', data_get($publication->payload,'tags',[])) ?: '—' }}</dd></div></dl></article><article class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><h2 class="text-xl font-black">Zaman çizelgesi</h2><dl class="mt-4 space-y-3 text-sm">@foreach([['Kuyruk',$publication->queued_at],['Başlangıç',$publication->started_at],['Yayın',$publication->published_at],['Tamamlanma',$publication->completed_at]] as [$label,$date])<div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $label }}</dt><dd>{{ $date?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>@endforeach</dl>@if($publication->remote_url)<a href="{{ $publication->remote_url }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-block text-cyan-300 hover:underline">Uzak yazıyı aç ↗</a>@endif</article></div></section></x-layouts.app>