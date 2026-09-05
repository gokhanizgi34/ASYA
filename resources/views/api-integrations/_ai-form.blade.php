@php
    $selectedProvider = App\IntegrationProvider::tryFrom((string) old('provider', request('provider', $integration?->provider?->value ?? App\IntegrationProvider::OpenAi->value))) ?? App\IntegrationProvider::OpenAi;
    $selectedProvider = $selectedProvider->usesSimpleSetup() ? $selectedProvider : App\IntegrationProvider::OpenAi;
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
</div>