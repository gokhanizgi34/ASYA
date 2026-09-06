<x-layouts.app title="API Sağlayıcısı Ekle">
    <section class="mx-auto max-w-2xl space-y-6">
        <header>
            <a href="{{ route('api-integrations.index') }}" class="text-sm font-semibold text-cyan-300">← Entegrasyonlar</a>
            <h1 class="mt-4 text-3xl font-black">API sağlayıcısı ekle</h1>
            <p class="mt-2 text-slate-400">Yapay zekâ, medya ve Google Search Console bağlantılarını ajans bazında güvenle yapılandırın.</p>
        </header>
        <form method="POST" action="{{ route('api-integrations.store') }}" class="space-y-6 rounded-2xl border border-white/10 bg-white/[.04] p-6">
            @csrf
            @include('api-integrations._ai-form', ['integration' => null])
            <button class="w-full rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950 hover:bg-cyan-300">Bağlantıyı kaydet</button>
        </form>
    </section>
</x-layouts.app>