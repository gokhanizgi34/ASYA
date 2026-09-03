<x-layouts.app title="Ajanslar">
    <section>
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p class="text-sm font-bold tracking-[.18em] text-cyan-300">ÇOKLU AJANS YÖNETİMİ</p><h1 class="mt-3 text-4xl font-black">Ajanslar</h1><p class="mt-2 text-slate-400">Ajans erişimlerini, aboneliklerini ve ekip sayılarını yönetin.</p></div>@can('create', App\Models\Agency::class)<a href="{{ route('agencies.create') }}" class="rounded-xl bg-cyan-300 px-5 py-3 text-center font-bold text-slate-950 hover:bg-cyan-200">+ Ajans ekle</a>@endcan</div>
        <div class="mt-8 grid gap-5 lg:grid-cols-2">
            @forelse ($agencies as $agency)
                <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6">
                    <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold">{{ $agency->name }}</h2><p class="mt-1 text-sm text-slate-400">{{ $agency->contact_email ?: 'İletişim e-postası yok' }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $agency->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-rose-400/10 text-rose-300' }}">{{ $agency->is_active ? 'Aktif' : 'Pasif' }}</span></div>
                    <dl class="mt-6 grid grid-cols-2 gap-4 border-y border-white/10 py-4 text-sm"><div><dt class="text-slate-500">Kullanıcı</dt><dd class="mt-1 font-bold">{{ $agency->users_count }}</dd></div><div><dt class="text-slate-500">Abonelik bitişi</dt><dd class="mt-1 font-bold">{{ $agency->subscription_ends_at?->format('d.m.Y') ?? 'Süresiz' }}</dd></div></dl>
                    <div class="mt-5 flex justify-end gap-2">@can('update', $agency)<a href="{{ route('agencies.edit', $agency) }}" class="rounded-lg border border-white/10 px-3 py-2 text-sm hover:bg-white/5">Düzenle</a>@endcan @can('updateStatus', $agency)<form method="POST" action="{{ route('agencies.status.update', $agency) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $agency->is_active ? 0 : 1 }}"><button class="rounded-lg border px-3 py-2 text-sm {{ $agency->is_active ? 'border-rose-400/20 text-rose-300' : 'border-emerald-400/20 text-emerald-300' }}">{{ $agency->is_active ? 'Pasif yap' : 'Aktif et' }}</button></form>@endcan</div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 p-12 text-center text-slate-400 lg:col-span-2">Henüz ajans yok.</div>
            @endforelse
        </div>
        @if ($agencies->hasPages())<div class="mt-6">{{ $agencies->links() }}</div>@endif
    </section>
</x-layouts.app>