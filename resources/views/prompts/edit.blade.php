<x-layouts.app title="Prompt Düzenle">
    <section class="mx-auto max-w-5xl space-y-7">
        <header><p class="text-sm font-bold tracking-[.18em] text-cyan-300">AI YÖNETİMİ · SÜRÜM {{ $prompt->version }}</p><h1 class="mt-3 text-4xl font-black">Prompt şablonunu düzenle</h1><p class="mt-2 text-slate-400">Kaydettiğinizde şablon sürümü otomatik olarak artırılır.</p></header>
        <form method="POST" action="{{ route('prompts.update', $prompt) }}" class="rounded-2xl border border-white/10 bg-white/[.04] p-5 sm:p-7">@csrf @method('PUT') @include('prompts._form', ['submitLabel' => 'Değişiklikleri kaydet'])</form>
    </section>
</x-layouts.app>