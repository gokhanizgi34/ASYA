<x-layouts.app title="API Entegrasyonunu Düzenle">
    <section class="mx-auto max-w-2xl space-y-6">
        <header>
            <a href="{{ route('api-integrations.index') }}" class="text-sm font-semibold text-cyan-300">← Entegrasyonlar</a>
            <h1 class="mt-4 text-3xl font-black">{{ $integration->name }}</h1>
        </header>
        <form method="POST" action="{{ route('api-integrations.update', $integration) }}" class="space-y-6 rounded-2xl border border-white/10 bg-white/[.04] p-6">
            @csrf
            @method('PUT')
            @if ($integration->provider->usesSimpleSetup())
                @include('api-integrations._ai-form')
            @else
                @include('api-integrations._form')
            @endif
            <button class="w-full rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950 hover:bg-cyan-300">Değişiklikleri kaydet</button>
        </form>
    </section>
</x-layouts.app>