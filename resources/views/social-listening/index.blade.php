<x-layouts.app title="Sosyal Dinleme">
<section class="space-y-8">
    <header>
        <p class="text-sm font-bold tracking-[.18em] text-sky-300">İTİBAR VE GÜNDEM RADARI</p>
        <h1 class="mt-3 text-4xl font-black">Sosyal Dinleme</h1>
        <p class="mt-2 text-slate-400">Marka, konu ve kişi bahislerini ajans bazında izleyin; duygu ve aciliyet sinyallerini editoryal incelemeye alın.</p>
    </header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['total' => 'Toplam bahis', 'negative' => 'Olumsuz', 'urgent' => 'Yüksek öncelik', 'open' => 'Açık inceleme'] as $key => $label)
            <article class="rounded-2xl border border-white/10 bg-white/[.03] p-5">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <strong class="mt-2 block text-3xl">{{ $summary[$key] }}</strong>
            </article>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('social-listening.store') }}" class="space-y-5 rounded-2xl border border-sky-400/20 bg-sky-400/[.04] p-6">
            @csrf
            <div><h2 class="text-xl font-black">Yeni dinleme kuralı</h2><p class="text-sm text-slate-500">Virgülle ayrılmış konu sözcükleri ve izlenecek platformları belirleyin.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>Ajans<select name="agency_id" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select></label>
                <label>Kural adı<input name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Anahtar kelimeler<input name="keywords" value="{{ old('keywords') }}" required placeholder="ASYA, yerel haber, belediye" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Hariç tutulan terimler<input name="excluded_terms" value="{{ old('excluded_terms') }}" placeholder="reklam, çekiliş" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <fieldset class="sm:col-span-2"><legend class="mb-2 font-semibold">Platformlar</legend><div class="flex flex-wrap gap-3">@foreach ($platforms as $platform)<label class="rounded-lg border border-white/10 px-3 py-2"><input type="checkbox" name="platforms[]" value="{{ $platform }}"> {{ strtoupper($platform) }}</label>@endforeach</div></fieldset>
                <label>Uyarı eşiği<input type="number" name="alert_threshold" value="{{ old('alert_threshold', 70) }}" min="1" max="100" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="self-end pb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
            </div>
            <button class="rounded-xl bg-sky-300 px-5 py-3 font-black text-slate-950">Kuralı kaydet</button>
        </form>

        <form method="POST" action="{{ route('social-mentions.store') }}" class="space-y-5 rounded-2xl border border-violet-400/20 bg-violet-400/[.04] p-6">
            @csrf
            <div><h2 class="text-xl font-black">Bahis ekle ve analiz et</h2><p class="text-sm text-slate-500">Faz 1'de doğrulanmış bir sosyal içeriği elle ekleyin. Otomatik akış bir sonraki modülde bağlanacaktır.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>Dinleme kuralı<select name="social_listening_watch_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($watches->where('is_active', true) as $watch)<option value="{{ $watch->id }}">{{ $watch->name }}</option>@endforeach</select></label>
                <label>Platform<select name="platform" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($platforms as $platform)<option value="{{ $platform }}">{{ strtoupper($platform) }}</option>@endforeach</select></label>
                <label>Yazar / hesap<input name="author_handle" value="{{ old('author_handle') }}" maxlength="120" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>Dış kimlik<input name="external_id" value="{{ old('external_id') }}" maxlength="190" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>Yayın zamanı<input type="datetime-local" name="published_at" value="{{ old('published_at', now()->format('Y-m-d\TH:i')) }}" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>Etkileşim<input type="number" name="engagement_count" value="{{ old('engagement_count', 0) }}" min="0" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Bağlantı<input type="url" name="url" value="{{ old('url') }}" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">İçerik<textarea name="content" rows="6" minlength="20" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">{{ old('content') }}</textarea></label>
            </div>
            <button class="rounded-xl bg-violet-300 px-5 py-3 font-black text-slate-950">Analiz ederek kaydet</button>
        </form>
    </div>

    <section class="space-y-4">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div><h2 class="text-2xl font-black">İzlenen bahisler</h2><p class="text-sm text-slate-500">{{ $watches->count() }} kural etkin veya kayıtlı.</p></div>
            <form method="GET" class="grid gap-2 sm:grid-cols-3">
                <select name="platform" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2"><option value="">Tüm platformlar</option>@foreach ($platforms as $platform)<option value="{{ $platform }}" @selected(request('platform') === $platform)>{{ strtoupper($platform) }}</option>@endforeach</select>
                <select name="sentiment" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2"><option value="">Tüm duygular</option>@foreach ($sentiments as $sentiment)<option value="{{ $sentiment->value }}" @selected(request('sentiment') === $sentiment->value)>{{ $sentiment->label() }}</option>@endforeach</select>
                <select name="status" onchange="this.form.submit()" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2"><option value="">Tüm durumlar</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            </form>
        </div>
        <div class="space-y-3">
            @forelse ($mentions as $mention)
                <article class="rounded-2xl border {{ $mention->urgency_score >= $mention->watch->alert_threshold ? 'border-rose-400/30 bg-rose-400/[.04]' : 'border-white/10 bg-white/[.03]' }} p-5">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-slate-800 px-2.5 py-1 font-bold">{{ strtoupper($mention->platform) }}</span>
                                <span class="{{ $mention->sentiment === App\SocialSentiment::Negative ? 'text-rose-300' : ($mention->sentiment === App\SocialSentiment::Positive ? 'text-emerald-300' : 'text-slate-400') }}">{{ $mention->sentiment->label() }}</span>
                                <span class="text-amber-300">Öncelik {{ $mention->urgency_score }}/100</span>
                                <span class="text-slate-500">{{ $mention->published_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <h3 class="mt-3 font-black">{{ $mention->title ?: $mention->author_handle ?: 'Adsız sosyal bahis' }}</h3>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-300">{{ $mention->content }}</p>
                            <p class="mt-3 text-xs text-sky-300">{{ implode(' · ', $mention->matched_keywords) }}</p>
                        </div>
                        <div class="shrink-0 space-y-2">
                            @if ($mention->url)<a href="{{ $mention->url }}" target="_blank" rel="noopener noreferrer" class="block rounded-lg border border-sky-400/20 px-3 py-2 text-center text-sm text-sky-300">Kaynağı aç</a>@endif
                            <form method="POST" action="{{ route('social-mentions.update', $mention) }}">@csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="rounded-lg border border-white/10 bg-slate-900 px-3 py-2 text-sm">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($mention->status === $status)>{{ $status->label() }}</option>@endforeach</select></form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400">Henüz eşleşen sosyal bahis yok.</div>
            @endforelse
        </div>
        {{ $mentions->links() }}
    </section>
</section>
</x-layouts.app>
