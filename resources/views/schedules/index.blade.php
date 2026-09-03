<x-layouts.app title="Planlama Takvimi">
<section class="space-y-7">
    <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold tracking-[.18em] text-sky-300">OTOMATİK İŞ TAKVİMİ</p>
            <h1 class="mt-3 text-4xl font-black">Zamanlayıcı ve Planlama</h1>
            <p class="mt-2 text-slate-400">Yayın işleri ile AI tarafından hazırlanan özel gün içerik planını tek ekrandan yönetin.</p>
        </div>
        <a href="{{ route('schedules.create') }}" class="rounded-xl bg-sky-300 px-5 py-3 text-center font-bold text-slate-950">+ Plan ekle</a>
    </header>

    @can('create', App\Models\ScheduleEntry::class)
        <form method="POST" action="{{ route('editorial-calendar.generate') }}" class="rounded-2xl border border-cyan-300/20 bg-cyan-300/[.05] p-5">
            @csrf
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="flex-1">
                    <h2 class="text-xl font-black">AI özel gün içerik takvimi</h2>
                    <p class="mt-1 text-sm text-slate-400">En fazla beş yıllık özel günleri ve aranma niyeti güçlü SEO konu önerilerini hazırlar.</p>
                </div>
                <label class="space-y-2">
                    <span class="block text-xs font-bold text-slate-300">Ajans</span>
                    <select name="agency_id" required class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                        @foreach($agencies as $agency)
                            <option value="{{ $agency->id }}" @selected((string) old('agency_id', auth()->user()->agency_id) === (string) $agency->id)>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-2">
                    <span class="block text-xs font-bold text-slate-300">Başlangıç yılı</span>
                    <input type="number" name="start_year" min="{{ now()->year }}" max="{{ now()->year + 5 }}" value="{{ old('start_year', now()->year) }}" required class="w-32 rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                </label>
                <label class="space-y-2">
                    <span class="block text-xs font-bold text-slate-300">Kaç yıl?</span>
                    <select name="years" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
                        @foreach(range(1, 5) as $yearCount)<option value="{{ $yearCount }}" @selected((int) old('years', 5) === $yearCount)>{{ $yearCount }} yıl</option>@endforeach
                    </select>
                </label>
                <button class="rounded-xl bg-cyan-300 px-5 py-3 font-bold text-slate-950">AI ile oluştur</button>
            </div>
            @error('editorial_calendar')<p class="mt-3 text-sm font-bold text-rose-300">{{ $message }}</p>@enderror
        </form>
    @endcan

    <div class="rounded-2xl border border-white/10 bg-white/[.04] p-5">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div><h2 class="text-xl font-black">Özel gün içerik planı</h2><p class="text-sm text-slate-500">Görselsiz içerik otomatik yayımlanmaz; kaynak görsel kuralı korunur.</p></div>
            <span class="rounded-full bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">{{ $editorialEvents->count() }} kayıt</span>
        </div>
        <div class="space-y-3">
            @forelse($editorialEvents as $event)
                <article class="grid gap-4 rounded-xl border border-white/10 bg-slate-950/40 p-4 md:grid-cols-[150px_1fr]">
                    <div><strong class="text-cyan-200">{{ $event->event_date->format('d.m.Y') }}</strong><span class="mt-1 block text-xs text-slate-500">Hazırlık: {{ $event->content_due_at->format('d.m.Y') }}</span></div>
                    <div><h3 class="font-black">{{ $event->title }}</h3><p class="mt-2 text-sm text-slate-400">{{ implode(' · ', $event->seo_topics) }}</p><span class="mt-2 inline-block text-xs text-slate-600">{{ $event->ai_provider }}</span></div>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-white/10 p-8 text-center text-slate-500">Henüz AI özel gün planı oluşturulmadı.</p>
            @endforelse
        </div>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 sm:grid-cols-3 lg:grid-cols-4">
        <input type="date" name="date" value="{{ request('date') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
        <select name="action" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3"><option value="">Tüm eylemler</option>@foreach($actions as $action)<option value="{{ $action->value }}" @selected(request('action') === $action->value)>{{ $action->label() }}</option>@endforeach</select>
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3"><option value="">Tüm durumlar</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        <button class="rounded-xl border border-sky-300/30 px-5 py-3 font-bold text-sky-200">Filtrele</button>
    </form>

    <div class="space-y-3">
        @forelse($entries as $entry)
            <a href="{{ route('schedules.show', $entry) }}" class="grid gap-4 rounded-2xl border border-white/10 bg-white/[.04] p-5 hover:border-sky-300/30 sm:grid-cols-[150px_1fr_auto] sm:items-center">
                <div><strong class="block text-lg text-sky-200">{{ $entry->scheduled_for->format('d.m.Y') }}</strong><span class="text-2xl font-black">{{ $entry->scheduled_for->format('H:i') }}</span></div>
                <div><span class="text-xs font-bold text-sky-300">{{ $entry->action->label() }}</span><h2 class="mt-1 text-lg font-black">{{ $entry->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->agency->name }} · {{ $entry->creator?->name ?? 'Sistem' }}</p></div>
                <span class="rounded-full bg-sky-400/10 px-3 py-1 text-xs font-bold text-sky-300">{{ $entry->status->label() }}</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400">Takvimde zamanlanmış iş bulunmuyor.</div>
        @endforelse
    </div>
    {{ $entries->links() }}
</section>
</x-layouts.app>
