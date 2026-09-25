<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ApplicationEventType;
use App\Enums\ForwardDelivery;
use App\Enums\ForwardStatus;
use App\Mail\CandidatesForwardedMail;
use App\Models\Application;
use App\Models\Forward;
use App\Models\ForwardApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Spec §7: computes the total CV size, picks attachments or 7-day signed
 * links (EF-1005), sends one email and records the outcome + `forwarded`
 * events on every candidate.
 */
class SendForwardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $forwardId)
    {
        $this->queue = 'mail';
    }

    public function handle(): void
    {
        $forward = Forward::with(['snapshots.application.analysis', 'user'])->find($this->forwardId);

        if ($forward === null) {
            return;
        }

        try {
            $candidates = [];
            $attachments = [];
            $totalBytes = 0;

            foreach ($forward->snapshots as $snapshot) {
                $application = $snapshot->application;
                if ($application !== null && $application->cv_path !== null) {
                    $totalBytes += (int) $application->cv_size;
                }
            }

            $maxBytes = (int) config('recruitment.forward.max_attachment_mb') * 1024 * 1024;
            $delivery = $totalBytes <= $maxBytes ? ForwardDelivery::Attachments : ForwardDelivery::Links;

            foreach ($forward->snapshots as $snapshot) {
                $candidates[] = $this->candidateRow($forward, $snapshot, $delivery, $attachments);
            }

            Mail::to($forward->to_emails)->send(
                new CandidatesForwardedMail(
                    $forward,
                    $candidates,
                    $attachments,
                    $delivery === ForwardDelivery::Links
                        ? 'Les CV sont trop volumineux pour être joints : des liens de téléchargement sécurisés (valables '
                            .config('recruitment.forward.link_ttl_days')
                            .' jours) sont inclus.'
                        : '',
                ),
            );

            $this->finalize($forward, ForwardStatus::Sent, $delivery, null);
        } catch (Throwable $exception) {
            Log::warning('forward_failed', ['forward_id' => $forward->id, 'error' => $exception->getMessage()]);
            $this->finalize($forward, ForwardStatus::Failed, null, $exception->getMessage());
        }
    }

    /**
     * @param  list<Attachment>  $attachments
     * @return array{name: string, path?: string, link?: string, analysis?: array<string, mixed>}
     */
    private function candidateRow(
        Forward $forward,
        ForwardApplication $snapshot,
        ForwardDelivery $delivery,
        array &$attachments,
    ): array {
        $application = $snapshot->application;
        $row = ['name' => $snapshot->candidate_name_snapshot];

        if ($application === null) {
            return $row;
        }

        if ($delivery === ForwardDelivery::Attachments) {
            if ($application->cv_path !== null) {
                $row['path'] = $application->cv_path;
                $attachments[] = Attachment::fromStorageDisk('private', $application->cv_path)
                    ->as($this->cvFilename($snapshot->candidate_name_snapshot, $application->cv_original_name));
            }
        } else {
            $row['link'] = URL::temporarySignedRoute(
                'forwarded-cv',
                now()->addDays((int) config('recruitment.forward.link_ttl_days')),
                ['application' => $application->id],
            );
        }

        if ($forward->include_analysis) {
            $analysis = $application->analysis;
            if ($analysis !== null) {
                $row['analysis'] = [
                    'match_score' => $analysis->match_score,
                    'summary' => $analysis->summary,
                ];
            }
        }

        return $row;
    }

    private function finalize(Forward $forward, ForwardStatus $status, ?ForwardDelivery $delivery, ?string $error): void
    {
        $attributes = [
            'status' => $status,
            'error_message' => $error,
            'sent_at' => $status === ForwardStatus::Sent ? now() : null,
        ];
        if ($delivery !== null) {
            $attributes['delivery'] = $delivery;
        }

        $forward->forceFill($attributes)->save();

        if ($status !== ForwardStatus::Sent) {
            return;
        }

        foreach ($forward->snapshots as $snapshot) {
            if ($snapshot->application_id === null) {
                continue;
            }

            /** @var Application $application */
            $application = $snapshot->application;
            $application->events()->create([
                'user_id' => $forward->user_id,
                'type' => ApplicationEventType::Forwarded,
                'payload' => ['forward_id' => $forward->id],
            ]);
        }
    }

    private function cvFilename(string $name, ?string $original): string
    {
        $extension = $original !== null ? pathinfo($original, PATHINFO_EXTENSION) : 'pdf';
        $slug = Str::slug($name, '_');

        return 'CV_'.$slug.($extension !== '' ? '.'.$extension : '');
    }
}
