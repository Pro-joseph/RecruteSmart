<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Forward;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One email per forward listing the selected candidates (spec §7.4, EF-1004). */
class CandidatesForwardedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, path?: string, link?: string, analysis?: array<string, mixed>}>  $candidates
     * @param  list<Attachment>  $cvAttachments
     */
    public function __construct(
        public readonly Forward $forward,
        public readonly array $candidates,
        public readonly array $cvAttachments = [],
        public readonly string $deliveryNote = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forward->subject,
            to: $this->forward->to_emails,
            replyTo: [$this->forward->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.candidates-forwarded',
            with: [
                'senderName' => $this->forward->user->name,
                'messageBody' => $this->forward->message,
                'candidates' => collect($this->candidates)->map(static fn (array $candidate): array => [
                    'name' => $candidate['name'],
                    'link' => $candidate['link'] ?? null,
                    'analysis' => $candidate['analysis'] ?? null,
                ])->all(),
                'deliveryNote' => $this->deliveryNote,
            ],
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->cvAttachments;
    }
}
