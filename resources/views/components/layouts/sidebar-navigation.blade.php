@foreach ($menuGroups as $group)
    @php($visibleItems = array_values(array_filter($group['items'], fn (array $item): bool => $item['allowed'])))
    @continue($visibleItems === [])

    <section class="mb-6">
        <h2 class="mb-2 px-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-600">{{ $group['label'] }}</h2>
        <div class="space-y-1">
            @foreach ($visibleItems as $item)
                @php($isActive = is_array($item['pattern']) ? request()->routeIs(...$item['pattern']) : request()->routeIs($item['pattern']))
                <a href="{{ route($item['route']) }}"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                       'bg-cyan-400/15 text-cyan-200 ring-1 ring-inset ring-cyan-400/20' => $isActive,
                       'text-slate-400 hover:bg-white/5 hover:text-slate-100' => ! $isActive,
                   ])
                   @if ($isActive) aria-current="page" @endif>
                    <span @class([
                        'h-2 w-2 shrink-0 rounded-full',
                        'bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,.7)]' => $isActive,
                        'bg-slate-700' => ! $isActive,
                    ])></span>
                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                    @if (($item['badge'] ?? 0) > 0)
                        <span class="rounded-full bg-rose-400 px-1.5 py-0.5 text-[10px] font-black text-slate-950">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endforeach
