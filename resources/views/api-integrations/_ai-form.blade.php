@php
    $selectedProvider = App\IntegrationProvider::tryFrom((string) old('provider', request('provider', $integration?->provider?->value ?? App\IntegrationProvider::OpenAi->value))) ?? App\IntegrationProvider::OpenAi;
    $selectedProvider = $selectedProvider->usesSimpleSetup() ? $selectedProvider : App\IntegrationProvider::OpenAi;
    $isSearchConsole = $selectedProvider === App\IntegrationProvider::GoogleSearchConsole;
    $savedSearchConsoleSiteUrl = $isSearchConsole && $integration?->model
        ? preg_replace('#/news-sitemap\.xml$#', '', $integration->model)
        : null;
    $selectedSiteUrl = old('site_url', $savedSearchConsoleSiteUrl ?? $publishingTargets->first()?->base_url);
@endphp

<div class="grid gap-6">
    @if (auth()->user()->isSystemAdministrator())
        <label>
            <span class="mb-2 block text-sm font-semibold">Ajans</span>
            <select name="agency_id" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                <option value="">Ajans seçin</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency->id }}" @selected((string) old('agency_id', $integration?->agency_id) === (string) $agency->id)>{{ $agency->name }}</option>
                @endforeach
            </select>
        </label>
    @else
        <input type="hidden" name="agency_id" value="{{ auth()->user()->agency_id }}">
    @endif

    @if ($isSearchConsole)
        <input type="hidden" name="provider" value="{{ App\IntegrationProvider::GoogleSearchConsole->value }}">

        <section class="grid gap-4 rounded-2xl border border-cyan-400/20 bg-cyan-400/5 p-5">
            <div>
                <strong class="text-lg text-cyan-200">Google’da bir defa yapılacaklar</strong>
                <p class="mt-1 text-sm text-slate-400">Butonlar doğrudan gerekli Google sayfalarını açar.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-cyan-300/20 bg-slate-950/60 px-4 py-3 text-center text-sm font-bold text-cyan-200 hover:border-cyan-300/50">1. API’yi etkinleştir ↗</a>
                <a href="https://console.cloud.google.com/iam-admin/serviceaccounts/create" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-cyan-300/20 bg-slate-950/60 px-4 py-3 text-center text-sm font-bold text-cyan-200 hover:border-cyan-300/50">2. Hizmet hesabı oluştur ↗</a>
                <a href="https://search.google.com/search-console/users" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-cyan-300/20 bg-slate-950/60 px-4 py-3 text-center text-sm font-bold text-cyan-200 hover:border-cyan-300/50">3. Search Console kullanıcıları ↗</a>
            </div>
            <ol class="list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-300">
                <li>İlk butondan Search Console API’yi <strong>Etkinleştir</strong>.</li>
                <li>İkinci butondan bir hizmet hesabı oluştur. Hesabı açıp <strong>Anahtarlar → Anahtar ekle → Yeni anahtar → JSON</strong> yoluyla dosyayı indir.</li>
                <li>Aşağıdan JSON dosyasını seç. ASYA’nın gösterdiği e-posta adresini üçüncü butondaki Search Console kullanıcılarına <strong>Tam kullanıcı</strong> olarak ekle.</li>
            </ol>
        </section>

        <label>
            <span class="mb-2 block text-sm font-semibold">WordPress sitesi</span>
            @if ($publishingTargets->isNotEmpty())
                <select name="site_url" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                    @if ($selectedSiteUrl && ! $publishingTargets->contains(fn ($target) => rtrim($target->base_url, '/') === rtrim($selectedSiteUrl, '/')))
                        <option value="{{ $selectedSiteUrl }}" selected>{{ $selectedSiteUrl }}</option>
                    @endif
                    @foreach ($publishingTargets as $target)
                        <option value="{{ rtrim($target->base_url, '/') }}" @selected(rtrim((string) $selectedSiteUrl, '/') === rtrim($target->base_url, '/'))>{{ $target->name }} · {{ $target->base_url }}@if(auth()->user()->isSystemAdministrator()) · {{ $target->agency->name }}@endif</option>
                    @endforeach
                </select>
                <small class="mt-2 block text-emerald-300/80">ASYA, Search Console mülkünü ve <code>/news-sitemap.xml</code> adresini otomatik hazırlar.</small>
            @else
                <input type="url" name="site_url" value="{{ $selectedSiteUrl }}" maxlength="1000" required placeholder="https://www.siteniz.com" class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                <small class="mt-2 block text-amber-200/80">Kayıtlı WordPress hedefi bulunamadı. Sitenizin ana adresini yazın.</small>
            @endif
        </label>

        <label>
            <span class="mb-2 block text-sm font-semibold">Google’dan indirdiğiniz JSON dosyası</span>
            <input type="file" accept=".json,application/json" data-search-console-json @required(! $integration) class="block w-full cursor-pointer rounded-xl border border-cyan-400/30 bg-slate-900 text-sm text-slate-300 file:mr-4 file:border-0 file:bg-cyan-300 file:px-5 file:py-3 file:font-bold file:text-slate-950 hover:file:bg-cyan-200">
            <small class="mt-2 block text-slate-500">Dosyayı açıp kopyalamanız gerekmez; yalnızca seçin.</small>
        </label>
        <textarea name="credential" data-search-console-credential class="hidden" aria-hidden="true"></textarea>
        <div data-search-console-email-card class="hidden rounded-xl border border-emerald-400/25 bg-emerald-400/10 p-4 text-sm text-emerald-100">
            <strong class="block">Search Console’a eklenecek e-posta</strong>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                <code data-search-console-email class="min-w-0 flex-1 break-all rounded-lg bg-slate-950/60 px-3 py-2"></code>
                <button type="button" data-copy-search-console-email class="rounded-lg border border-emerald-300/30 px-3 py-2 font-bold">Kopyala</button>
            </div>
        </div>
        @if ($integration)
            <p class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-3 text-sm text-slate-300">Mevcut JSON anahtarı korunur. Yalnızca değiştirmek istiyorsanız yeni dosya seçin.</p>
        @endif
        <input type="hidden" name="visual_enabled" value="0">
    @else
        <label>
            <span class="mb-2 block text-sm font-semibold">API sağlayıcısı</span>
            <select name="provider" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
                @foreach ($aiProviders as $provider)
                    <option value="{{ $provider->value }}" @selected($selectedProvider === $provider)>{{ $provider->label() }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="mb-2 block text-sm font-semibold">API anahtarı</span>
            <input type="password" name="credential" maxlength="4000" @required(! $integration) autocomplete="new-password" placeholder="{{ $integration ? 'Değişmeyecekse boş bırakın' : 'API anahtarını buraya yapıştırın' }}" class="w-full rounded-xl border border-cyan-400/30 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
            <small class="mt-2 block text-emerald-300/80">Anahtar şifreli saklanır. API adresi ve bağlantı ayarları otomatik hazırlanır.</small>
        </label>

        @if ($selectedProvider === App\IntegrationProvider::Pixabay)
            <input type="hidden" name="visual_enabled" value="1">
            <p class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 px-4 py-3 text-sm text-emerald-200">Pixabay yalnızca tarif ve içerik görselleri için kullanılacaktır; metin yapay zekâ sıralamasına katılmaz.</p>
        @else
            <label class="flex items-center gap-3 rounded-xl border border-amber-400/20 bg-amber-400/5 px-4 py-3">
                <input type="hidden" name="visual_enabled" value="0">
                <input type="checkbox" name="visual_enabled" value="1" @checked((bool) old('visual_enabled', $integration?->visual_enabled ?? false))>
                <span><strong class="block">Görsel üretiminde kullanılabilir</strong><small class="text-slate-500">Haber metni API’leri varsayılan olarak görsel üretiminden ayrı tutulur.</small></span>
            </label>
        @endif
    @endif
</div>

@if ($isSearchConsole)
<script>
    (() => {
        const fileInput = document.querySelector('[data-search-console-json]');
        const credentialInput = document.querySelector('[data-search-console-credential]');
        const emailCard = document.querySelector('[data-search-console-email-card]');
        const emailOutput = document.querySelector('[data-search-console-email]');
        const copyButton = document.querySelector('[data-copy-search-console-email]');

        if (!fileInput || !credentialInput || !emailCard || !emailOutput) {
            return;
        }

        fileInput.addEventListener('change', async () => {
            fileInput.setCustomValidity('');
            emailCard.classList.add('hidden');
            credentialInput.value = '';

            const file = fileInput.files?.[0];
            if (!file) {
                return;
            }

            try {
                const content = await file.text();
                const credentials = JSON.parse(content);

                if (credentials.type !== 'service_account' || !credentials.client_email || !credentials.private_key) {
                    throw new Error('invalid-service-account');
                }

                credentialInput.value = content;
                emailOutput.textContent = credentials.client_email;
                emailCard.classList.remove('hidden');
            } catch (error) {
                fileInput.setCustomValidity('Bu dosya geçerli bir Google hizmet hesabı JSON dosyası değil.');
                fileInput.reportValidity();
            }
        });

        copyButton?.addEventListener('click', async () => {
            await navigator.clipboard.writeText(emailOutput.textContent ?? '');
            copyButton.textContent = 'Kopyalandı';
        });
    })();
</script>
@endif
