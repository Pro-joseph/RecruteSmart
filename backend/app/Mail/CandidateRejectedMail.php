<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Refusal email sent on demand when the recruiter ticks « envoyer un email » (EF-905). */
class CandidateRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
        public readonly ?string $message = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre candidature — {$this->application->offer->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidate-rejected',
            with: [
                'name' => $this->application->full_name,
                'offerTitle' => $this->application->offer->title,
                'extraMessage' => $this->message,
            ],
        );
    }
}
