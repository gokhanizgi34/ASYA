<x-layouts.app title="Yetki Matrisi">
<section class="space-y-7">
    <header>
        <p class="text-sm font-bold tracking-[.18em] text-violet-300">ERİŞİM DENETİMİ</p>
        <h1 class="mt-3 text-4xl font-black">Yetki Matrisi</h1>
        <p class="mt-2 text-slate-400">Her rolün gerçek Laravel policy kararlarını modül ve işlem bazında denetleyin.</p>
    </header>

    <div class="overflow-x-auto rounded-2xl border border-white/10">
        <table class="min-w-[1050px] w-full text-left text-sm">
            <thead class="bg-white/[.05] text-slate-300">
                <tr><th class="p-4">Modül / İşlem</th>@foreach($roles as $role)<th class="p-4 text-center">{{ $role->label() }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-white/10">
                @foreach($rows as $row)
                    <tr class="bg-white/[.02]"><th colspan="{{ count($roles) + 1 }}" class="p-4 text-base text-cyan-200">{{ $row['label'] }}</th></tr>
                    @foreach($row['permissions'][App\UserRole::SystemAdministrator->value] as $ability => $allowed)
                        <tr class="hover:bg-white/[.025]">
                            <td class="px-4 py-3 text-slate-400">{{ match($ability) { 'viewAny' => 'Görüntüle', 'create' => 'Oluştur', 'update', 'updateAny' => 'Düzenle', 'delete' => 'Sil', default => $ability } }}</td>
                            @foreach($roles as $role)
                                @php($granted = $row['permissions'][$role->value][$ability])
                                <td class="px-4 py-3 text-center"><span class="inline-flex min-w-20 justify-center rounded-full px-2.5 py-1 text-xs font-bold {{ $granted ? 'bg-emerald-400/15 text-emerald-300' : 'bg-slate-700/40 text-slate-500' }}">{{ $granted ? 'İzinli' : 'Kapalı' }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-sm text-slate-500">Matris salt okunurdur ve sayfa açıldığında policy sınıflarından yeniden hesaplanır. Yetki değişiklikleri kod incelemesi ve testlerle uygulanır.</p>
</section>
</x-layouts.app>
