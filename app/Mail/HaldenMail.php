<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A plain email from Halden: a subject and a few paragraphs, wording from the content pack.
 */
class HaldenMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines */
    public function __construct(public string $subjectLine, public array $lines) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.halden-text', with: ['lines' => $this->lines]);
    }
}
