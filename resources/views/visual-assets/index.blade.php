<x-layouts.app title="AI Haber Görseli">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">TELİF · KALİTE · KAPAK</p><h1 class="mt-3 text-4xl font-black">AI Haber Görseli</h1><p class="mt-2 text-slate-400">Görsel kalitesini ve telif güvenini denetleyin; arşiv veya özgün üretim yoluyla haber kapağını seçin.</p></div><a href="{{ route('visual-assets.create') }}" class="rounded-xl bg-fuchsia-300 px-5 py-3 text-center font-bold text-slate-950 hover:bg-fuchsia-200">+ Görsel ekle</a></header>
        <div class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full {{ $aiGenerationEnabled ? 'bg-emerald-300' : 'bg-slate-500' }}"></span>
                        <h2 class="text-lg font-black">AI görsel motoru {{ $aiGenerationEnabled ? 'aktif' : 'kapalı' }}</h2>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">Kaynak sitedeki görsel her zaman önce kullanılır. Motor aktifse yalnızca kaynak görsel bulunamadığında AI görsel üretir.</p>
                </div>
                @can('updateAny', App\Models\SystemSetting::class)
                    <form method="POST" action="{{ route('visual-assets.ai-status') }}" class="flex flex-col gap-2 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        @if(auth()->user()->isSystemAdministrator())
                            <select name="agency_id" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                                @foreach($settingAgencies as $agency)
                                    <option value="{{ $agency->id }}" @selected($settingsAgencyId === $agency->id)>{{ $agency->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="agency_id" value="{{ $settingsAgencyId }}">
                        @endif
                        <button name="enabled" value="{{ $aiGenerationEnabled ? 0 : 1 }}" class="rounded-xl px-5 py-3 font-bold {{ $aiGenerationEnabled ? 'border border-rose-300/30 text-rose-200 hover:bg-rose-300/10' : 'bg-emerald-300 text-slate-950 hover:bg-emerald-200' }}">
                            {{ $aiGenerationEnabled ? 'AI görsel motorunu kapat' : 'AI görsel motorunu aç' }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@foreach($statuses as $status)<a href="{{ route('visual-assets.index', ['status' => $status->value]) }}" class="rounded-xl border p-4 {{ request('status') === $status->value ? 'border-fuchsia-300/50 bg-fuchsia-300/10' : 'border-white/10 bg-white/[.04]' }}"><span class="text-xs text-slate-400">{{ $status->label() }}</span><strong class="mt-2 block text-2xl">{{ $statusCounts[$status->value] }}</strong></a>@endforeach</div>
        <form method="GET" action="{{ route('visual-assets.index') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 md:grid-cols-[1fr_220px_220px_auto]"><input name="q" value="{{ request('q') }}" placeholder="Görsel, alt metin veya manşet ara" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-fuchsia-300"><select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-fuchsia-300"><option value="">Tüm durumlar</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select><select name="source_type" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-fuchsia-300"><option value="">Tüm kaynaklar</option>@foreach($sourceTypes as $sourceType)<option value="{{ $sourceType->value }}" @selected(request('source_type') === $sourceType->value)>{{ $sourceType->label() }}</option>@endforeach</select><button class="rounded-xl border border-fuchsia-300/30 px-5 py-3 font-bold text-fuchsia-200 hover:bg-fuchsia-300/10">Filtrele</button></form>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($assets as $asset)<article class="overflow-hidden rounded-2xl border {{ $asset->is_selected ? 'border-emerald-300/50' : 'border-white/10' }} bg-white/[.04]"><a href="{{ route('visual-assets.show', $asset) }}" class="relative block aspect-video overflow-hidden bg-slate-900">@if($asset->storage_path)<img src="{{ route('visual-assets.file', $asset) }}" alt="{{ $asset->alt_text }}" class="h-full w-full object-cover">@elseif($asset->source_url)<img src="{{ $asset->source_url }}" alt="{{ $asset->alt_text }}" referrerpolicy="no-referrer" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center bg-gradient-to-br from-fuchsia-950 to-slate-900 text-sm text-fuchsia-200">AI üretim isteği bekliyor</div>@endif @if($asset->headline_overlay)<strong class="absolute inset-x-0 bottom-0 bg-slate-950/85 p-4 text-lg leading-tight">{{ $asset->headline_overlay }}</strong>@endif</a><div class="p-5"><div class="flex items-start justify-between gap-3"><div><a href="{{ route('visual-assets.show', $asset) }}" class="font-black hover:text-fuchsia-300">{{ $asset->title }}</a><p class="mt-1 text-xs text-slate-500">{{ $asset->agency->name }} · {{ $asset->source_type->label() }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ match($asset->status) { App\VisualAssetStatus::Approved => 'bg-emerald-400/10 text-emerald-300', App\VisualAssetStatus::NeedsReplacement, App\VisualAssetStatus::Failed => 'bg-rose-400/10 text-rose-300', App\VisualAssetStatus::Generating => 'bg-fuchsia-400/10 text-fuchsia-300', default => 'bg-amber-400/10 text-amber-300' } }}">{{ $asset->status->label() }}</span></div><div class="mt-4 flex items-center justify-between text-sm"><span class="text-slate-400">Kalite <strong class="text-white">{{ $asset->quality_score }}/100</strong></span>@if($asset->is_selected)<span class="font-bold text-emerald-300">Aktif kapak</span>@endif</div></div></article>@empty<div class="rounded-2xl border border-dashed border-white/15 p-12 text-center text-slate-400 sm:col-span-2 xl:col-span-3">Bu filtrelerle eşleşen görsel bulunamadı.</div>@endforelse</div>
        @if($assets->hasPages())<div>{{ $assets->links() }}</div>@endif
    </section>
</x-layouts.app>