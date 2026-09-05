<x-layouts.app :title="$letter->pseudonym">
<article class="mx-auto max-w-4xl space-y-7"><header><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">MUKADDES ABLA</p><h1 class="mt-3 text-4xl font-black">{{ $letter->pseudonym }}</h1><p class="mt-2 text-slate-400">{{ $letter->agency->name }} · {{ $letter->status->label() }} · Risk: {{ $letter->risk_level->label() }}</p></header>
<section class="whitespace-pre-line rounded-2xl border border-white/10 bg-white/[.04] p-6 text-lg leading-8">{{ $letter->question }}</section>
@if($letter->response_body)<section class="rounded-2xl border border-fuchsia-300/20 bg-fuchsia-300/5 p-6"><h2 class="text-2xl font-black">{{ $letter->response_title }}</h2><div class="mt-4 whitespace-pre-line leading-7 text-slate-200">{{ $letter->response_body }}</div></section>@endif
<div class="flex flex-wrap gap-3"><a href="{{ route('advice-letters.index') }}" class="rounded-xl border border-white/10 px-5 py-3">Listeye dön</a>@can('update',$letter)<a href="{{ route('advice-letters.edit',$letter) }}" class="rounded-xl bg-fuchsia-300 px-5 py-3 font-black text-slate-950">Yanıtla / düzenle</a>@endcan</div>
</article>
</x-layouts.app>
