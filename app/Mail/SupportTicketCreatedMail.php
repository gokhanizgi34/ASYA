<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Yeni destek talebi: '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.support-ticket-created');
    }

    public function attachments(): array
    {
        return [];
    }
}
