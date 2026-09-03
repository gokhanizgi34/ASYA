<x-layouts.app title="Sistem Ayarları">
    <section class="space-y-7">
        <header class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-sm font-bold tracking-[.18em] text-violet-300">MERKEZİ YAPILANDIRMA</p>
                <h1 class="mt-3 text-4xl font-black">Sistem Ayarları</h1>
                <p class="mt-2 max-w-3xl text-slate-400">Genel çalışma kurallarını belirleyin; ajans bazında yalnızca gerekli değerleri geçersiz kılın.</p>
            </div>
            <form method="GET" action="{{ route('system-settings.index') }}" class="w-full rounded-2xl border border-white/10 bg-white/[.04] p-4 lg:w-80">
                <label>
                    <span class="mb-1 block text-xs font-semibold text-slate-400">Ayar kapsamı</span>
                    <select name="agency_id" onchange="this.form.submit()" class="w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm">
                        @if (auth()->user()->isSystemAdministrator())<option value="">Sistem varsayılanları</option>@endif
                        @foreach ($agencies as $agency)
                            <option value="{{ $agency->id }}" @selected((string) $agencyId === (string) $agency->id)>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        </header>

        <div class="rounded-2xl border px-5 py-4 {{ $isSystemScope ? 'border-violet-400/25 bg-violet-400/10 text-violet-100' : 'border-cyan-400/25 bg-cyan-400/10 text-cyan-100' }}">
            @if ($isSystemScope)
                Bu değerler tüm ajansların varsayılanıdır. Ajans özelinde değiştirilmiş alanlar etkilenmez.
            @else
                “Sistemden devral” seçili alanlar, sistem varsayılanı değiştiğinde otomatik olarak güncellenir.
            @endif
        </div>

        <form method="POST" action="{{ route('system-settings.update') }}" class="space-y-6">
            @csrf @method('PUT')
            @if ($agencyId !== null)<input type="hidden" name="agency_id" value="{{ $agencyId }}">@endif

            @foreach ($groupedSettings as $group => $items)
                <fieldset class="rounded-2xl border border-white/10 bg-white/[.04] p-5 sm:p-6">
                    <legend class="px-2 text-lg font-bold">{{ $group }}</legend>
                    <div class="mt-2 grid gap-5 lg:grid-cols-2">
                        @foreach ($items as $item)
                            <div class="rounded-xl border border-white/10 bg-slate-900/55 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <label for="{{ $item['field'] }}" class="font-semibold text-white">{{ $item['label'] }}</label>
                                        <p class="mt-1 text-sm text-slate-400">{{ $item['description'] }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-white/5 px-2 py-1 text-[11px] text-slate-400">{{ $item['source'] }}</span>
                                </div>

                                <div class="mt-4">
                                    @if ($item['type'] === App\SettingValueType::Boolean)
                                        <input type="hidden" name="settings[{{ $item['field'] }}]" value="0">
                                        <label class="inline-flex cursor-pointer items-center gap-3">
                                            <input id="{{ $item['field'] }}" type="checkbox" name="settings[{{ $item['field'] }}]" value="1" @checked((bool) old('settings.'.$item['field'], $item['value'])) class="h-5 w-5 rounded border-white/20 bg-slate-800 text-cyan-400">
                                            <span class="text-sm text-slate-300">Etkin</span>
                                        </label>
                                    @elseif ($item['type'] === App\SettingValueType::Select)
                                        <select id="{{ $item['field'] }}" name="settings[{{ $item['field'] }}]" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                                            @foreach ($item['options'] as $value => $label)<option value="{{ $value }}" @selected((string) old('settings.'.$item['field'], $item['value']) === (string) $value)>{{ $label }}</option>@endforeach
                                        </select>
                                    @elseif ($item['type'] === App\SettingValueType::Integer)
                                        <input id="{{ $item['field'] }}" type="number" name="settings[{{ $item['field'] }}]" value="{{ old('settings.'.$item['field'], $item['value']) }}" min="{{ $item['min'] }}" max="{{ $item['max'] }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-cyan-400">
                                    @else
                                        <input id="{{ $item['field'] }}" name="settings[{{ $item['field'] }}]" value="{{ old('settings.'.$item['field'], $item['value']) }}" maxlength="80" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-cyan-400">
                                    @endif
                                </div>

                                @if (! $isSystemScope)
                                    <div class="mt-4 border-t border-white/10 pt-3">
                                        <input type="hidden" name="inherit[{{ $item['field'] }}]" value="0">
                                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-slate-400">
                                            <input type="checkbox" name="inherit[{{ $item['field'] }}]" value="1" @checked($item['inherited']) class="rounded border-white/20 bg-slate-800 text-violet-400">
                                            Sistemden devral
                                        </label>
                                    </div>
                                @endif
                                @error('settings.'.$item['field'])<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="sticky bottom-4 flex justify-end rounded-2xl border border-white/10 bg-slate-950/90 p-4 shadow-2xl backdrop-blur">
                <button class="rounded-xl bg-violet-400 px-6 py-3 text-sm font-black text-slate-950 hover:bg-violet-300">Ayarları kaydet</button>
            </div>
        </form>
    </section>
</x-layouts.app>
