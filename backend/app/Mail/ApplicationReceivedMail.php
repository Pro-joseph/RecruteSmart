<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Application $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Candidature bien reçue — {$this->application->offer->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-received',
            with: [
                'name' => $this->application->full_name,
                'offerTitle' => $this->application->offer->title,
            ],
        );
    }
}
