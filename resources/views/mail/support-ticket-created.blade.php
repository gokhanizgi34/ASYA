<x-mail::message>
# Yeni destek talebi

**Talep:** {{ $ticket->ticket_number }}  
**Ajans:** {{ $ticket->agency?->name ?? 'Sistem' }}  
**Gönderen:** {{ $ticket->requester?->name }}  
**Öncelik:** {{ $ticket->priority->label() }}  
**Konu:** {{ $ticket->subject }}

{{ $ticket->message }}

<x-mail::button :url="route('support-tickets.show', $ticket)">
Talebi görüntüle
</x-mail::button>
</x-mail::message>
