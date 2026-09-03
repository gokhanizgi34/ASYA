<x-layouts.app title="Entegrasyonlar">
<section class="space-y-10">
<header><p class="text-sm font-bold tracking-[.18em] text-cyan-300">AJANS ENTEGRASYON MERKEZİ</p><h1 class="mt-3 text-4xl font-black">Entegrasyonlar</h1><p class="mt-2 text-slate-400">Her ajans kendi yapay zekâ ve e-posta bağlantısını birkaç alanla kurabilir.</p></header>
<section><div class="flex items-end justify-between gap-4"><div><h2 class="text-2xl font-black">Yapay zekâ sağlayıcısı ekle</h2><p class="mt-1 text-sm text-slate-400">Birden fazla sağlayıcı ekleyebilirsiniz. Sağlayıcıyı seçin ve yalnız API anahtarını girin; teknik ayarlar otomatik tamamlanır.</p></div></div><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach($aiProviders as $provider)<a href="{{ route('api-integrations.create',['provider'=>$provider->value]) }}" class="rounded-2xl border border-white/10 bg-white/[.04] p-4 hover:border-cyan-400/40"><strong>{{ $provider->label() }}</strong><small class="mt-1 block text-slate-500">API anahtarı ekle →</small></a>@endforeach</div></section>
<section><div class="flex items-center justify-between"><h2 class="text-2xl font-black">Kayıtlı API bağlantıları</h2><a href="{{ route('api-integrations.create') }}" class="rounded-xl bg-cyan-400 px-4 py-2 font-bold text-slate-950">Yapay zekâ API ekle</a></div><div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">@forelse($integrations as $integration)<article class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div class="flex justify-between gap-3"><div><span class="text-xs font-bold text-cyan-300">{{ $integration->provider->label() }}</span><h3 class="mt-1 text-xl font-bold">{{ $integration->name }}</h3><small class="text-slate-500">{{ $integration->agency->name }}</small></div>@if($integration->is_default)<span class="h-fit rounded-full bg-violet-400/15 px-2.5 py-1 text-xs text-violet-300">Varsayılan</span>@endif</div>@if($integration->model)<p class="mt-3 text-sm"><span class="text-slate-500">Model:</span> {{ $integration->model }}</p>@endif<p class="mt-3 break-all rounded-xl bg-slate-900/70 p-3 font-mono text-xs text-slate-400">{{ $integration->base_url }}</p>@if($integration->last_error)<p class="mt-3 text-sm text-rose-300">{{ $integration->last_error }}</p>@endif<div class="mt-4 flex flex-wrap gap-2"><form method="POST" action="{{ route('api-integrations.test',$integration) }}">@csrf<button class="rounded-xl bg-violet-400 px-3 py-2 text-sm font-bold text-slate-950">Test et</button></form><a href="{{ route('api-integrations.edit',$integration) }}" class="rounded-xl border border-white/10 px-3 py-2 text-sm">Düzenle</a><form method="POST" action="{{ route('api-integrations.destroy',$integration) }}">@csrf @method('DELETE')<button class="rounded-xl border border-rose-400/20 px-3 py-2 text-sm text-rose-300">Sil</button></form></div></article>@empty<div class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-slate-500 md:col-span-2 xl:col-span-3">Henüz bağlantı yok. Yukarıdan sağlayıcı seçin.</div>@endforelse</div><div class="mt-5">{{ $integrations->links() }}</div></section>
<section class="rounded-3xl border border-cyan-400/20 bg-cyan-400/[.04] p-6">
<h2 class="text-2xl font-black">E-posta ve bildirim entegrasyonu</h2>
<p class="mt-2 text-slate-400">Destek talepleri ve güvenli hata uyarıları bu adrese gelir. Her ajans için ayrı SMTP tanımlanabilir.</p>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
@foreach($mailSettings as $setting)
<article class="rounded-2xl border border-white/10 bg-slate-950/50 p-5">
<div class="mb-4 flex justify-between gap-3"><strong>{{ $setting->agency?->name ?? 'Sistem geneli' }}</strong><span class="text-xs text-slate-500">{{ $setting->last_tested_at?->diffForHumans() ?? 'Test edilmedi' }}</span></div>
<form method="POST" action="{{ route('agency-mail-settings.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf<input type="hidden" name="agency_id" value="{{ $setting->agency_id }}">@include('api-integrations._mail-form',['setting'=>$setting])<button class="rounded-xl bg-cyan-400 px-4 py-2 font-bold text-slate-950 sm:col-span-2">Ayarları kaydet</button></form>
<form method="POST" action="{{ route('agency-mail-settings.test',$setting) }}" class="mt-3">@csrf<button class="rounded-xl border border-white/10 px-4 py-2">Test e-postası gönder</button></form>
</article>
@endforeach
<details class="rounded-2xl border border-dashed border-white/15 p-5" @if($mailSettings->isEmpty()) open @endif>
<summary class="cursor-pointer font-bold">+ Yeni ajans e-posta bağlantısı</summary>
<form method="POST" action="{{ route('agency-mail-settings.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
<label class="sm:col-span-2"><span class="text-sm">Ajans</span><select name="agency_id" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@if(auth()->user()->isSystemAdministrator())<option value="">Sistem geneli</option>@endif @foreach($mailAgencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select></label>
@include('api-integrations._mail-form',['setting'=>null])
<button class="rounded-xl bg-cyan-400 px-4 py-2 font-bold text-slate-950 sm:col-span-2">E-posta bağlantısını kaydet</button>
</form>
</details>
</div>
</section>
</section>
</x-layouts.app>
