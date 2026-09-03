<x-layouts.app title="Başlangıç">
    <section class="mx-auto grid min-h-[75vh] max-w-6xl items-center gap-12 lg:grid-cols-[1.2fr_.8fr]">
        <div>
            <span class="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-4 py-2 text-xs font-bold tracking-[.22em] text-cyan-200">HABER ÜRETİMİNİN MERKEZİ</span>
            <h1 class="mt-7 max-w-3xl text-5xl font-black leading-[1.05] tracking-tight sm:text-7xl">İçeriği yöneten <span class="text-cyan-300">akıllı haber operasyonu.</span></h1>
            <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">ASYA; kullanıcı, haber, yayın ve ajans süreçlerini tek merkezde birleştirmek üzere kuruluyor. Faz 1 kullanıcı ve yetki altyapısıyla başladı.</p>
            <a href="{{ route('login') }}" class="mt-9 inline-flex rounded-xl bg-cyan-300 px-6 py-3 font-bold text-slate-950 shadow-lg shadow-cyan-400/20 hover:bg-cyan-200">Sisteme giriş yap</a>
        </div>
        <div class="rounded-3xl border border-white/10 bg-white/[.04] p-7 shadow-2xl shadow-black/30 backdrop-blur">
            <div class="flex items-center justify-between"><span class="text-sm text-slate-400">Kurulum durumu</span><span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300">FAZ 1</span></div>
            <div class="mt-8 space-y-4">
                @foreach ([['Kimlik doğrulama', 'Hazır'], ['Rol ve yetki temeli', 'Hazır'], ['Aktif / pasif kullanıcı', 'Hazır'], ['Haber operasyonu', 'Sonraki faz']] as [$item, $status])
                    <div class="flex items-center justify-between border-b border-white/10 pb-4"><span>{{ $item }}</span><span class="text-sm text-slate-400">{{ $status }}</span></div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.app>