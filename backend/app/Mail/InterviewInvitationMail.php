<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Interview;
use App\Services\Interviews\IcsCalendar;
use App\Services\Mails\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?array $copy = null;

    public function __construct(public readonly Interview $interview) {}

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

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $interview = $this->interview;

        return [
            Attachment::fromData(
                static fn (): string => app(IcsCalendar::class)->build($interview),
                'invitation.ics',
            )->withMime('text/calendar; charset=utf-8'),
        ];
    }

    /** @return array{subject: string, body: string} */
    private function copy(): array
    {
        if ($this->copy === null) {
            $interview = $this->interview;
            $application = $interview->application;

            $this->copy = app(EmailTemplateRenderer::class)->render(
                $interview->creator,
                EmailTemplate::INVITATION,
                [
                    'candidate_name' => $application->full_name,
                    'offer_title' => $application->offer->title,
                    'starts_at' => $interview->starts_at->format('d/m/Y à H:i'),
                    'duration' => (string) $interview->duration_minutes,
                    'location' => $interview->location_or_link,
                ],
            );
        }

        return $this->copy;
    }
}
