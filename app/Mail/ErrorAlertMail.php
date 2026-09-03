<?php

namespace App\Mail;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ErrorLog $errorLog) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ASYA sistem hatası: '.$this->errorLog->severity->label());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.error-alert');
    }

    public function attachments(): array
    {
        return [];
    }
}
