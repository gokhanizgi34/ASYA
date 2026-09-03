<x-mail::message>
# Yeni sistem hatası kaydedildi

**Kayıt No:** #{{ $errorLog->id }}  
**Önem:** {{ $errorLog->severity->label() }}  
**Zaman:** {{ $errorLog->last_seen_at?->format('d.m.Y H:i:s') }}

Güvenlik nedeniyle hata ayrıntıları e-postaya eklenmemiştir. Ayrıntıyı yalnızca yetkili ASYA panelinden inceleyin.

<x-mail::button :url="route('error-logs.show', $errorLog)">
Güvenli panelde görüntüle
</x-mail::button>
</x-mail::message>
