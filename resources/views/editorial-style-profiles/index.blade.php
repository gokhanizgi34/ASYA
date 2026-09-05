<x-layouts.app title="Yazım Dili Hafızası">
    <section class="space-y-7">
        <header>
            <p class="text-sm font-bold tracking-[.18em] text-cyan-300">MALİYET ODAKLI ÖZGÜNLEŞTİRME</p>
            <h1 class="mt-3 text-4xl font-black">Yazım Dili Hafızası</h1>
            <p class="mt-2 max-w-3xl text-slate-400">Ajans dilinden örnekleri, tercih edilen kelimeleri ve dönüşüm kurallarını kaydedin. Yerel motor yeterli olduğunda AI tokenı kullanmadan metni özgünleştirir; yetersiz kalırsa metin AI motoruna devreder.</p>
        </header>

        <div class="grid gap-6">
            @forelse ($agencies as $agency)
                @php($profile = $profiles->firstWhere('agency_id', $agency->id))
                <form method="POST" action="{{ route('editorial-style-profiles.update') }}" class="rounded-2xl border border-white/10 bg-white/[.04] p-5 sm:p-6">
                    @csrf @method('PUT')
                    <input type="hidden" name="agency_id" value="{{ $agency->id }}">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div><h2 class="text-2xl font-black">{{ $agency->name }}</h2><p class="mt-1 text-sm text-slate-400">Günlük yerel özgünleştirme kotası ve çıkış yönü bu ajansa özeldir.</p></div>
                        <label class="inline-flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('agency_id') == $agency->id ? old('is_active') : ($profile?->is_active ?? true)) class="h-5 w-5 rounded border-white/20 bg-slate-900 text-cyan-400"><span>Yerel motor aktif</span></label>
                    </div>
                    <div class="mt-6 grid gap-5 lg:grid-cols-2">
                        <label><span class="mb-2 block text-sm font-bold">Profil adı</span><input name="name" value="{{ old('agency_id') == $agency->id ? old('name') : ($profile?->name ?? $agency->name.' yazım dili') }}" required maxlength="120" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
                        <label><span class="mb-2 block text-sm font-bold">Günlük yerel üretim kotası</span><input type="number" name="daily_quota" min="0" max="500" value="{{ old('agency_id') == $agency->id ? old('daily_quota') : ($profile?->daily_quota ?? 50) }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></label>
                        <label><span class="mb-2 block text-sm font-bold">Başarılı sonuç nereye gitsin?</span><select name="destination" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"><option value="publish" @selected((old('agency_id') == $agency->id ? old('destination') : ($profile?->destination ?? 'publish')) === 'publish')>Doğrudan Yayın Merkezi</option><option value="draft" @selected((old('agency_id') == $agency->id ? old('destination') : $profile?->destination) === 'draft')>Taslak olarak beklesin</option></select></label>
                        <label><span class="mb-2 block text-sm font-bold">Tercih edilen kelimeler</span><textarea name="preferred_terms" rows="3" placeholder="ilçe, vatandaş, çalışma" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('agency_id') == $agency->id ? old('preferred_terms') : implode(', ', $profile?->learned_terms ?? []) }}</textarea></label>
                        <label class="lg:col-span-2"><span class="mb-2 block text-sm font-bold">Örnek haber metinleri</span><textarea name="sample_text" rows="8" placeholder="Beğendiğiniz kurumsal haber dili örneklerini buraya yapıştırın." class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('agency_id') == $agency->id ? old('sample_text') : $profile?->sample_text }}</textarea></label>
                        <label><span class="mb-2 block text-sm font-bold">Kelime dönüşümleri</span><textarea name="replacements_text" rows="6" placeholder="duyuruda => açıklamada&#10;kamuoyuyla paylaşıldı => açıklandı" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('agency_id') == $agency->id ? old('replacements_text') : collect($profile?->replacements ?? [])->map(fn ($to, $from) => $from.' => '.$to)->implode("\n") }}</textarea><p class="mt-2 text-xs text-slate-500">Her satırda eski ifade => yeni ifade biçimini kullanın. Yerel üretim için en az iki kural gerekir.</p></label>
                        <label><span class="mb-2 block text-sm font-bold">Kullanılmayacak ifadeler</span><textarea name="forbidden_terms_text" rows="6" placeholder="kaynağına göre, kamuoyuyla paylaşıldı" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('agency_id') == $agency->id ? old('forbidden_terms_text') : implode(', ', $profile?->forbidden_terms ?? []) }}</textarea></label>
                    </div>
                    @if ($errors->any() && old('agency_id') == $agency->id)
                        <div class="mt-5 rounded-xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-200">
                            <ul class="list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mt-6 flex justify-end"><button class="rounded-xl bg-cyan-300 px-6 py-3 font-black text-slate-950 hover:bg-cyan-200">Hafızayı kaydet</button></div>
                </form>
            @empty
                <div class="rounded-2xl border border-white/10 p-6 text-slate-400">Yazım dili tanımlanabilecek aktif ajans bulunamadı.</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
