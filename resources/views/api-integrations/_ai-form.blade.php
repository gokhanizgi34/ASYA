@php
    $selectedProvider = App\IntegrationProvider::tryFrom((string) old('provider', request('provider', $integration?->provider?->value ?? App\IntegrationProvider::OpenAi->value))) ?? App\IntegrationProvider::OpenAi;
    $selectedProvider = ($selectedProvider->isAi() || $selectedProvider === App\IntegrationProvider::XTrends) ? $selectedProvider : App\IntegrationProvider::OpenAi;
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
        <span class="mb-2 block text-sm font-semibold">AI / trend sağlayıcısı</span>
        <select name="provider" required class="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
            @foreach ($aiProviders as $provider)
                <option value="{{ $provider->value }}" @selected($selectedProvider === $provider)>{{ $provider->label() }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="mb-2 block text-sm font-semibold">API anahtarı</span>
        <input type="password" name="credential" maxlength="4000" @required(! $integration) autocomplete="new-password" placeholder="{{ $integration ? 'Değişmeyecekse boş bırakın' : 'API anahtarını buraya yapıştırın' }}" class="w-full rounded-xl border border-cyan-400/30 bg-slate-900 px-4 py-3 outline-none focus:border-cyan-300">
        <small class="mt-2 block text-emerald-300/80">Anahtar şifreli saklanır. Model, API adresi ve bağlantı ayarları otomatik hazırlanır.</small>
    </label>
</div>