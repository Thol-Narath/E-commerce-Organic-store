<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Simple email sent to confirm SMTP delivery from the admin settings page.
 *
 * Delivered synchronously so the admin sees the real send result (a failure
 * is reported instead of silently swallowed).
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $storeName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from '.$this->storeName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-email',
        );
    }
}