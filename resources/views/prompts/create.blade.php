<x-layouts.app title="Prompt Oluştur">
    <section class="mx-auto max-w-5xl space-y-7">
        <header><p class="text-sm font-bold tracking-[.18em] text-cyan-300">AI YÖNETİMİ</p><h1 class="mt-3 text-4xl font-black">Yeni prompt şablonu</h1><p class="mt-2 text-slate-400">İçerik üretimi için kapsamı, tonu ve model davranışını tanımlayın.</p></header>
        <form method="POST" action="{{ route('prompts.store') }}" class="rounded-2xl border border-white/10 bg-white/[.04] p-5 sm:p-7">@csrf @include('prompts._form', ['submitLabel' => 'Şablonu oluştur'])</form>
    </section>
</x-layouts.app>