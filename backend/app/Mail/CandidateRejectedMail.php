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

/** Refusal email sent on demand when the recruiter ticks « envoyer un email » (EF-905). */
class CandidateRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $copy = null;

    public function __construct(
        public readonly Application $application,
        public readonly ?string $message = null,
    ) {}

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
            $render = app(EmailTemplateRenderer::class)->render(
                $this->application->offer->user,
                EmailTemplate::REFUSAL,
                [
                    'candidate_name' => $this->application->full_name,
                    'offer_title' => $this->application->offer->title,
                ],
            );

            if ($this->message !== null && $this->message !== '') {
                $render['body'] .= "\n\n".$this->message;
            }

            $this->copy = $render;
        }

        return $this->copy;
    }
}
