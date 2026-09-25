<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * EF-1102: daily summary of the new applications received by a recruiter
 * in the last 24 hours (one email per recruiter, skipped when empty).
 */
class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{offer_id: int, offer_title: string, count: int}>  $offers
     */
    public function __construct(
        public readonly array $offers,
        public readonly int $total,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Récapitulatif quotidien — {$this->total} nouvelle".($this->total > 1 ? 's' : '').' candidature'.($this->total > 1 ? 's' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-digest',
            with: [
                'offers' => $this->offers,
                'total' => $this->total,
            ],
        );
    }
}
