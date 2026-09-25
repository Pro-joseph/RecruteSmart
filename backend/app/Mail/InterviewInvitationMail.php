<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Interview;
use App\Services\Interviews\IcsCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Interview $interview) {}

    public function envelope(): Envelope
    {
        $offer = $this->interview->application->offer;

        return new Envelope(
            subject: "Invitation à un entretien — {$offer->title}",
        );
    }

    public function content(): Content
    {
        $interview = $this->interview;
        $application = $interview->application;

        return new Content(
            view: 'emails.interview-invitation',
            with: [
                'name' => $application->full_name,
                'offerTitle' => $application->offer->title,
                'startsAt' => $interview->starts_at->format('d/m/Y à H:i'),
                'duration' => $interview->duration_minutes,
                'type' => $interview->type->value,
                'location' => $interview->location_or_link,
            ],
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
}
