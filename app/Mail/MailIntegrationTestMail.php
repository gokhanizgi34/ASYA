<?php

namespace App\Mail;

use App\Models\AgencyMailSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailIntegrationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AgencyMailSetting $setting) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ASYA e-posta entegrasyonu testi');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.integration-test');
    }

    public function attachments(): array
    {
        return [];
    }
}
