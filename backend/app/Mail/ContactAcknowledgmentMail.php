<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the customer to confirm that their contact message was
 * received. Delivered synchronously so delivery failures are logged and
 * surface immediately instead of being silently dropped.
 */
class ContactAcknowledgmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contactMessage,
        public string $storeName,
        public string $storeContactEmail = '',
        public string $storeContactPhone = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your message: '.$this->contactMessage->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-acknowledgment',
        );
    }
}