<x-layouts.app title="Veritabanı Yedekleri">
<section class="space-y-7">
    <header>
        <p class="text-sm font-bold tracking-[.18em] text-cyan-300">SİSTEM GÜVENLİĞİ</p>
        <h1 class="mt-3 text-4xl font-black">Veritabanı Yedekleri</h1>
        <p class="mt-2 text-slate-400">Şifreli SQLite anlık görüntüleri oluşturun, bütünlüğünü doğrulayın ve gerektiğinde güvenli şekilde indirin.</p>
    </header>

    <form method="POST" action="{{ route('database-backups.store') }}" class="grid gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-5 sm:grid-cols-[1fr_auto]">
        @csrf
        <label><span class="mb-1 block text-sm font-semibold">Yedek etiketi (isteğe bağlı)</span><input name="label" value="{{ old('label') }}" maxlength="100" placeholder="Örn. sürüm yükseltme öncesi" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
        <button class="self-end rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">Şifreli yedek oluştur</button>
    </form>

    <div class="rounded-2xl border border-amber-400/20 bg-amber-400/5 p-4 text-sm text-amber-100">
        Yedekler uygulama anahtarıyla şifrelenir ve yalnızca sistem yöneticisi tarafından indirilebilir. Geri yükleme, çalışan veritabanının üzerine yazılmaması için sunucu bakım sürecinde kontrollü yapılmalıdır.
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10">
        <div class="divide-y divide-white/10">
            @forelse($backups as $backup)
                <article class="grid gap-4 bg-white/[.03] p-5 lg:grid-cols-[1fr_140px_160px_auto] lg:items-center">
                    <div class="min-w-0"><strong class="block truncate">{{ $backup->label ?: $backup->original_filename }}</strong><small class="block text-slate-500">{{ $backup->original_filename }} · {{ $backup->creator?->name ?: 'Sistem' }} · {{ $backup->created_at->format('d.m.Y H:i') }}</small></div>
                    <span class="{{ $backup->status === App\DatabaseBackupStatus::Completed ? 'text-emerald-300' : 'text-rose-300' }}">{{ $backup->status->label() }}</span>
                    <span class="text-sm text-slate-400">{{ $backup->size_bytes ? number_format($backup->size_bytes / 1024, 1, ',', '.') . ' KB' : '—' }}<br>{{ $backup->verified_at ? 'Doğrulandı '.$backup->verified_at->format('d.m H:i') : 'Doğrulanmadı' }}</span>
                    <div class="flex flex-wrap gap-2">
                        @if($backup->status === App\DatabaseBackupStatus::Completed)
                            <form method="POST" action="{{ route('database-backups.verify', $backup) }}">@csrf<button class="rounded-lg border border-emerald-400/20 px-3 py-2 text-sm text-emerald-300">Doğrula</button></form>
                            <a href="{{ route('database-backups.download', $backup) }}" class="rounded-lg border border-cyan-400/20 px-3 py-2 text-sm text-cyan-300">İndir</a>
                        @endif
                        <form method="POST" action="{{ route('database-backups.destroy', $backup) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-400/20 px-3 py-2 text-sm text-rose-300">Sil</button></form>
                    </div>
                    @if($backup->failure_message)<p class="text-sm text-rose-300 lg:col-span-4">{{ $backup->failure_message }}</p>@endif
                </article>
            @empty
                <div class="p-14 text-center text-slate-400">Henüz veritabanı yedeği oluşturulmadı.</div>
            @endforelse
        </div>
    </div>
    {{ $backups->links() }}
</section>
</x-layouts.app>
