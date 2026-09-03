<x-layouts.app title="Kara Liste">
<section class="space-y-7">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold tracking-[.18em] text-rose-300">İÇERİK GÜVENLİĞİ</p>
            <h1 class="mt-3 text-4xl font-black">Kara Liste</h1>
            <p class="mt-2 text-slate-400">Sakıncalı kelime, kaynak, alan adı ve URL kalıplarını ham haber girişinde denetleyin.</p>
        </div>
        <a href="{{ route('blacklist-rules.create') }}" class="rounded-xl bg-rose-400 px-5 py-3 text-center font-black text-slate-950">Yeni kural</a>
    </header>

    <form method="GET" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.03] p-4 md:grid-cols-4">
        <input name="q" value="{{ request('q') }}" placeholder="Kural veya gerekçe ara" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">
        <select name="type" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"><option value="">Tüm türler</option>@foreach($types as $type)<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
        <select name="action" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"><option value="">Tüm işlemler</option>@foreach($actions as $action)<option value="{{ $action->value }}" @selected(request('action') === $action->value)>{{ $action->label() }}</option>@endforeach</select>
        <button class="rounded-xl border border-cyan-400/30 px-4 py-2.5 font-bold text-cyan-300">Filtrele</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10">
        <div class="divide-y divide-white/10">
            @forelse($rules as $rule)
                <article class="grid gap-4 bg-white/[.03] p-5 lg:grid-cols-[120px_1fr_150px_130px_auto] lg:items-center">
                    <span class="w-fit rounded-full bg-violet-400/15 px-2.5 py-1 text-xs text-violet-300">{{ $rule->type->label() }}</span>
                    <div class="min-w-0"><strong class="block break-all">{{ $rule->pattern }}</strong><small class="block text-slate-500">{{ $rule->reason ?: 'Gerekçe belirtilmedi' }} · {{ $rule->agency->name }}</small></div>
                    <span class="{{ $rule->action === App\BlacklistAction::Block ? 'text-rose-300' : 'text-amber-300' }}">{{ $rule->action->label() }}</span>
                    <span class="text-sm text-slate-400">{{ $rule->hit_count }} eşleşme<br>{{ $rule->is_active ? 'Etkin' : 'Pasif' }}</span>
                    <div class="flex gap-2"><a href="{{ route('blacklist-rules.edit', $rule) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm">Düzenle</a><form method="POST" action="{{ route('blacklist-rules.destroy', $rule) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-400/20 px-3 py-2 text-sm text-rose-300">Sil</button></form></div>
                </article>
            @empty
                <div class="p-14 text-center text-slate-400">Henüz kara liste kuralı yok.</div>
            @endforelse
        </div>
    </div>
    {{ $rules->links() }}
</section>
</x-layouts.app>
