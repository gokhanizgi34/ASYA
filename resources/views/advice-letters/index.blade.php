<x-layouts.app title="Mukaddes Abla">
<section class="space-y-7">
<header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-fuchsia-300">OKUR DANIŞMA MASASI</p><h1 class="mt-3 text-4xl font-black">Mukaddes Abla</h1><p class="mt-2 text-slate-400">Okur mektuplarını güvenli biçimde yönetin, yanıtlayın ve izin verilenleri yayınlayın.</p></div><a href="{{ route('advice-letters.create') }}" class="rounded-xl bg-fuchsia-300 px-5 py-3 font-black text-slate-950">Yeni mektup</a></header>
<div class="grid gap-4">@forelse($letters as $letter)<a href="{{ route('advice-letters.show', $letter) }}" class="rounded-2xl border border-white/10 bg-white/[.04] p-5 hover:border-fuchsia-300/30"><div class="flex justify-between gap-4"><div><h2 class="font-black">{{ $letter->pseudonym }}</h2><p class="mt-1 text-sm text-slate-400">{{ $letter->agency->name }} · {{ $letter->category }}</p></div><span class="text-sm text-fuchsia-300">{{ $letter->status->label() }}</span></div><p class="mt-4 line-clamp-2 text-slate-300">{{ $letter->question }}</p></a>@empty<div class="rounded-2xl border border-white/10 p-6 text-slate-400">Henüz mektup bulunmuyor.</div>@endforelse</div>
{{ $letters->links() }}
</section>
</x-layouts.app>
