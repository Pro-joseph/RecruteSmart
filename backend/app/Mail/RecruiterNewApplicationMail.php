<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** In-app alternative is out of scope: EF-1101 is email-only for this lot. */
class RecruiterNewApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Application $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouvelle candidature — {$this->application->offer->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recruiter-new-application',
            with: [
                'offerTitle' => $this->application->offer->title,
                'candidateName' => $this->application->full_name,
                'applicationUrl' => "/app/offers/{$this->application->offer_id}",
            ],
        );
    }
}
