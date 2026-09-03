<x-layouts.app title="Tarif Havuzu">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div><p class="text-sm font-bold tracking-[.18em] text-amber-300">GÜNLÜK MENÜ</p><h1 class="mt-3 text-4xl font-black">Tarif Havuzu</h1><p class="mt-2 text-slate-400">Tarifleri inceleyin, düzenleyin ve yayın merkezine gönderin.</p></div>
            @can('create', App\Models\Recipe::class)<a href="{{ route('recipes.create') }}" class="rounded-xl bg-amber-300 px-5 py-3 font-black text-slate-950">+ Tarif ekle</a>@endcan
        </header>
        <div class="flex flex-wrap gap-2">@foreach($categories as $key => $label)<a href="{{ route('recipes.index', ['category' => $key]) }}" class="rounded-xl border px-4 py-2 text-sm {{ request('category') === $key ? 'border-amber-300/50 bg-amber-300/10 text-amber-200' : 'border-white/10 text-slate-300' }}">{{ $label }}</a>@endforeach</div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($recipes as $recipe)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-5"><div class="flex items-start justify-between gap-3"><span class="text-xs font-bold uppercase tracking-wider text-amber-300">{{ $categories[$recipe->category] ?? $recipe->category }}</span>@if($recipe->is_active)<span class="text-xs text-emerald-300">Aktif</span>@else<span class="text-xs text-slate-500">Pasif</span>@endif</div><h2 class="mt-3 text-xl font-black">{{ $recipe->title }}</h2><p class="mt-3 line-clamp-3 text-sm text-slate-400">{{ $recipe->ingredients }}</p><div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('recipes.show', $recipe) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm">İncele</a><a href="{{ route('recipes.edit', $recipe) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm">Düzenle</a><form method="POST" action="{{ route('recipes.publish', $recipe) }}" class="flex flex-1 gap-2">@csrf@if(auth()->user()->isSystemAdministrator())<select name="agency_id" required class="min-w-0 flex-1 rounded-lg border border-white/10 bg-slate-900 px-2 py-2 text-xs">@foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select>@endif<button class="rounded-lg bg-emerald-300 px-3 py-2 text-sm font-bold text-slate-950">Yayın merkezine gönder</button></form></div></article>
            @empty<div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400 md:col-span-2 xl:col-span-3">Henüz tarif eklenmedi.</div>@endforelse
        </div>
        {{ $recipes->links() }}
    </section>
</x-layouts.app>
