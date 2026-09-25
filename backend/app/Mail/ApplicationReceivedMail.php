<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Application;
use App\Models\EmailTemplate;
use App\Services\Mails\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $copy = null;

    public function __construct(public readonly Application $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rendered',
            with: ['body' => $this->copy()['body']],
        );
    }

    /** @return array{subject: string, body: string} */
    private function copy(): array
    {
        if ($this->copy === null) {
            $this->copy = app(EmailTemplateRenderer::class)->render(
                $this->application->offer->user,
                EmailTemplate::CONFIRMATION,
                [
                    'candidate_name' => $this->application->full_name,
                    'offer_title' => $this->application->offer->title,
                ],
            );
        }

        return $this->copy;
    }
}
