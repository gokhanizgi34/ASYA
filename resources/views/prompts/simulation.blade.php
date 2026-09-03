<x-layouts.app title="Prompt Simülasyonu">
<section class="mx-auto max-w-5xl space-y-7">
    <header><a href="{{ route('prompts.index') }}" class="text-cyan-300">← Promptlar</a><p class="mt-5 text-sm font-bold tracking-[.18em] text-violet-300">GÜVENLİ ÖNİZLEME</p><h1 class="mt-3 text-4xl font-black">{{ $prompt->name }}</h1><p class="mt-2 text-slate-400">v{{ $prompt->version }} · {{ $prompt->agency?->name ?? 'Global sistem şablonu' }} · Harici AI servisine veri gönderilmez.</p></header>

    <form method="POST" action="{{ route('prompts.simulation.run', $prompt) }}" class="space-y-5 rounded-2xl border border-white/10 bg-white/[.04] p-6">
        @csrf
        <h2 class="text-xl font-black">Örnek değişkenler</h2>
        @foreach($variables as $variable)
            <label class="block"><span class="mb-1 block text-sm font-semibold">{<span>{{ $variable }}</span>}</span><textarea name="variables[{{ $variable }}]" rows="{{ $variable === 'content' ? 8 : 3 }}" maxlength="10000" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">{{ old('variables.'.$variable, $values[$variable] ?? '') }}</textarea></label>
        @endforeach
        <button class="rounded-xl bg-violet-300 px-6 py-3 font-black text-slate-950">Simülasyonu çalıştır</button>
    </form>

    @if($result)
        <div class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-2xl border border-cyan-400/20 bg-cyan-400/[.04] p-5"><h2 class="text-lg font-black text-cyan-200">Sistem promptu</h2><pre class="mt-4 whitespace-pre-wrap break-words font-sans text-sm text-slate-300">{{ $result['system_prompt'] }}</pre></article>
            <article class="rounded-2xl border border-violet-400/20 bg-violet-400/[.04] p-5"><h2 class="text-lg font-black text-violet-200">Kullanıcı promptu</h2><pre class="mt-4 whitespace-pre-wrap break-words font-sans text-sm text-slate-300">{{ $result['user_prompt'] }}</pre></article>
        </div>
        <p class="text-right text-sm text-slate-500">Toplam {{ number_format($result['character_count'], 0, ',', '.') }} karakter · Simülasyon {{ $prompt->last_tested_at?->format('d.m.Y H:i:s') }}</p>
    @endif
</section>
</x-layouts.app>
