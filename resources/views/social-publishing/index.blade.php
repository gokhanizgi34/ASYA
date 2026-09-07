<x-layouts.app title="Sosyal Otomatik Yayıncı">
<section class="space-y-8">
    <header><p class="text-sm font-bold tracking-[.18em] text-emerald-300">KUYRUKLU SOSYAL YAYIN</p><h1 class="mt-3 text-4xl font-black">Sosyal Otomatik Yayıncı</h1><p class="mt-2 text-slate-400">Her WordPress yayını başarıyla tamamlandığında haber bağlantısını otomatik olarak X hesabınıza gönderin. Belediye haberlerinde yalnız tanımladığınız resmî muhatap etiketlenir.</p></header>

    <div class="grid gap-6 xl:grid-cols-2">
        @can('create', App\Models\SocialPublishingAccount::class)
        <form method="POST" action="{{ route('social-publishing.accounts.store') }}" class="space-y-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/[.03] p-6">
            @csrf
            <h2 class="text-xl font-black">Yayın hesabı ekle</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>Ajans<select name="agency_id" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select></label>
                <label>Hesap adı<input name="name" required maxlength="120" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>Platform<select name="platform" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach (['x','instagram','facebook','youtube','tiktok','linkedin'] as $platform)<option value="{{ $platform }}">{{ strtoupper($platform) }}</option>@endforeach</select></label>
                <label>Hesap kullanıcı adı<input name="account_handle" required maxlength="120" placeholder="@asya" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Access Token <small class="text-slate-500">(OAuth 1.0a önerilir; OAuth 2 kullanıcı anahtarı da kullanılabilir)</small><input type="password" name="access_token" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>API Key <small class="text-slate-500">(OAuth 1.0a)</small><input type="password" name="api_key" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>API Key Secret <small class="text-slate-500">(OAuth 1.0a)</small><input type="password" name="api_secret" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Access Token Secret <small class="text-slate-500">(OAuth 1.0a)</small><input type="password" name="access_token_secret" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Belediye etiket eşlemeleri <small class="text-slate-500">(her satıra bir kural)</small><textarea name="mention_rules_text" rows="5" maxlength="5000" placeholder="Pendik Belediyesi=@Pendik_Belediye&#10;Sultanbeyli Belediyesi=@SultanbeyliBel" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></textarea><small class="mt-1 block text-slate-500">Yalnız belediye geçen haberlerde eşleşen hesap eklenir. X kaynağındaki kişi veya kurum haberde açıkça geçiyorsa kaynak hesabı da otomatik tanınır.</small></label>
                <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
                <div class="grid gap-2 text-sm sm:col-span-2"><a href="https://console.x.com/" target="_blank" rel="noopener noreferrer" class="w-fit font-bold text-cyan-300">X Developer Console’u aç ↗</a><p class="rounded-xl border border-amber-300/20 bg-amber-300/5 px-3 py-2 text-amber-100">Dört OAuth 1.0a alanını birlikte girin; tüm anahtarlar şifreli saklanır. X API ücretlidir; bağlantılı gönderiler mevcut tarifede daha yüksek ücretlendirilebilir; Developer Console’da kredi ve harcama limiti belirleyin.</p></div>
            </div>
            <button class="rounded-xl bg-emerald-300 px-5 py-3 font-black text-slate-950">Şifreli kasaya ekle</button>
        </form>
        @endcan

        <form method="POST" action="{{ route('social-publishing.posts.store') }}" class="space-y-4 rounded-2xl border border-cyan-400/20 bg-cyan-400/[.03] p-6">
            @csrf
            <h2 class="text-xl font-black">Gönderi taslağı</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>Yayın hesabı<select name="social_publishing_account_id" required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach ($accounts->where('is_active', true) as $account)<option value="{{ $account->id }}">{{ $account->name }} · {{ strtoupper($account->platform) }}</option>@endforeach</select></label>
                <label>Bağlı haber<select name="article_id" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"><option value="">Bağımsız gönderi</option>@foreach ($articles as $article)<option value="{{ $article->id }}">{{ $article->title }}</option>@endforeach</select></label>
                <label class="sm:col-span-2">Gönderi metni<textarea name="content" rows="5" required maxlength="5000" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">{{ old('content') }}</textarea></label>
                <label>Bağlantı<input type="url" name="link_url" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label>Medya URL<input type="url" name="media_url" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                <label class="sm:col-span-2">Planlanan zaman<input type="datetime-local" name="scheduled_for" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
            </div>
            <button class="rounded-xl bg-cyan-300 px-5 py-3 font-black text-slate-950">Taslağı oluştur</button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($accounts as $account)
            <article class="rounded-2xl border border-white/10 bg-white/[.03] p-5">
                <div class="flex justify-between gap-3"><h2 class="font-black">{{ $account->name }}</h2><span class="text-xs text-emerald-300">{{ $account->publish_mode === 'local_sandbox' ? 'Yerel güvenli' : 'Canlı X API' }}</span></div>
                <p class="mt-2 text-sm text-slate-400">{{ strtoupper($account->platform) }} · {{ $account->account_handle }}</p>
                <p class="mt-3 text-xs text-slate-500">{{ $account->posts_count }} gönderi · {{ count($account->mention_rules ?? []) }} belediye etiketi</p>
                @if ($account->platform === 'x' && auth()->user()->can('update', $account))
                    <details class="mt-4 rounded-xl border border-white/10 p-3">
                        <summary class="cursor-pointer font-bold text-cyan-300">X bağlantısını ve etiketleri düzenle</summary>
                        <form method="POST" action="{{ route('social-publishing.accounts.update', $account) }}" class="mt-4 grid gap-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="agency_id" value="{{ $account->agency_id }}">
                            <input type="hidden" name="platform" value="x">
                            <label>Hesap adı<input name="name" value="{{ $account->name }}" required maxlength="120" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>X kullanıcı adı<input name="account_handle" value="{{ $account->account_handle }}" required maxlength="16" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>Yeni Access Token <small class="text-slate-500">(değişmeyecekse boş)</small><input type="password" name="access_token" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>Yeni API Key <small class="text-slate-500">(değişmeyecekse boş)</small><input type="password" name="api_key" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>Yeni API Key Secret <small class="text-slate-500">(değişmeyecekse boş)</small><input type="password" name="api_secret" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>Yeni Access Token Secret <small class="text-slate-500">(değişmeyecekse boş)</small><input type="password" name="access_token_secret" minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5"></label>
                            <label>Belediye etiket eşlemeleri<textarea name="mention_rules_text" rows="5" maxlength="5000" class="mt-1 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5">@foreach (($account->mention_rules ?? []) as $institution => $handle){{ $institution }}={{ $handle }}@if (! $loop->last){{ "\n" }}@endif @endforeach</textarea></label>
                            <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Aktif</label>
                            <button class="rounded-xl bg-cyan-300 px-4 py-2.5 font-black text-slate-950">X ayarlarını kaydet</button>
                        </form>
                    </details>
                @endif
            </article>
        @endforeach
    </div>

    <section class="space-y-4"><h2 class="text-2xl font-black">Gönderiler</h2><div class="space-y-3">
        @forelse ($posts as $post)
            <article class="rounded-2xl border border-white/10 bg-white/[.03] p-5"><div class="flex flex-col justify-between gap-4 sm:flex-row"><div><div class="flex flex-wrap gap-2 text-xs"><span class="text-emerald-300">{{ strtoupper($post->account->platform) }}</span><span>{{ $post->status->label() }}</span>@if ($post->scheduled_for)<span class="text-amber-300">{{ $post->scheduled_for->format('d.m.Y H:i') }}</span>@endif</div><p class="mt-3 whitespace-pre-wrap text-slate-200">{{ $post->content }}</p>@if ($post->error_message)<p class="mt-2 text-sm text-rose-300">{{ $post->error_message }}</p>@endif</div>@if (in_array($post->status, [App\SocialPostStatus::Draft, App\SocialPostStatus::Failed], true))<form method="POST" action="{{ route('social-posts.dispatch', $post) }}">@csrf<button class="rounded-xl bg-emerald-300 px-4 py-2.5 font-black text-slate-950">{{ $post->scheduled_for?->isFuture() ? 'Zamanla' : 'Şimdi kuyruğa al' }}</button></form>@endif</div></article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/15 p-14 text-center text-slate-400">Henüz sosyal gönderi yok.</div>
        @endforelse
    </div>{{ $posts->links() }}</section>
</section>
</x-layouts.app>
