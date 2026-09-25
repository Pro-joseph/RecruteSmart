<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicationUpdater
{
    /**
     * Applies a status change (EF-703, any transition allowed) and records
     * a status_changed event. Returns false when the status was unchanged.
     *
     * @param  array<string, mixed>  $extraPayload  merged into the history payload
     */
    public function changeStatus(
        Application $application,
        ApplicationStatus $to,
        User $user,
        array $extraPayload = [],
    ): bool {
        $from = $application->status;

        if ($from === $to) {
            return false;
        }

        DB::transaction(function () use ($application, $extraPayload, $from, $to, $user): void {
            $application->status = $to;
            $application->save();

            $application->events()->create([
                'user_id' => $user->id,
                'type' => ApplicationEventType::StatusChanged,
                'payload' => array_merge(
                    ['from' => $from->value, 'to' => $to->value],
                    $extraPayload,
                ),
            ]);
        });

        return true;
    }

    /**
     * Refuses a candidate with an internal reason (EF-905); the optional
     * candidate-facing email is queued by the caller.
     */
    public function reject(Application $application, string $reason, User $user): bool
    {
        return $this->changeStatus($application, ApplicationStatus::Rejected, $user, [
            'reason' => $reason,
        ]);
    }

    /**
     * Stores an internal note and/or a 1-5 rating (EF-704) plus its
     * note_added history entry (EF-705).
     */
    public function addNote(Application $application, ?string $note, ?int $rating, User $user): void
    {
        DB::transaction(function () use ($application, $note, $rating, $user): void {
            if ($rating !== null) {
                $application->rating = $rating;
                $application->save();
            }

            $payload = array_filter(
                ['note' => $note, 'rating' => $rating],
                static fn (mixed $value): bool => $value !== null,
            );

            $application->events()->create([
                'user_id' => $user->id,
                'type' => ApplicationEventType::NoteAdded,
                'payload' => $payload,
            ]);
        });
    }
}
