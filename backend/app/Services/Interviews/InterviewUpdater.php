<?php

declare(strict_types=1);

namespace App\Services\Interviews;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\InterviewDecision;
use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InterviewUpdater
{
    /**
     * Follows up an interview (EF-904): status, report (notes) and decision.
     * A « proceed » decision moves the application to « offer », a « reject »
     * one to « rejected » (UC-04).
     *
     * @param array{
     *     status?: string,
     *     starts_at?: string,
     *     duration_minutes?: int,
     *     location_or_link?: string|null,
     *     notes?: string|null,
     *     decision?: string
     * } $data
     */
    public function update(Interview $interview, array $data, User $user): Interview
    {
        $interview->loadMissing('application.offer');

        DB::transaction(function () use ($interview, $data, $user): void {
            if (isset($data['status'])) {
                $interview->status = InterviewStatus::from($data['status']);
            }
            if (isset($data['starts_at'])) {
                $interview->starts_at = CarbonImmutable::parse($data['starts_at']);
            }
            if (array_key_exists('duration_minutes', $data)) {
                $interview->duration_minutes = $data['duration_minutes'];
            }
            if (array_key_exists('location_or_link', $data)) {
                $interview->location_or_link = $data['location_or_link'];
            }
            if (array_key_exists('notes', $data)) {
                $interview->notes = $data['notes'];
            }
            if (isset($data['decision'])) {
                $interview->decision = InterviewDecision::from($data['decision']);
            }

            $interview->save();

            $application = $interview->application;
            $application->events()->create([
                'user_id' => $user->id,
                'type' => ApplicationEventType::InterviewUpdated,
                'payload' => array_filter([
                    'interview_id' => $interview->id,
                    'status' => $interview->status->value,
                    'decision' => $interview->decision?->value,
                ], static fn (mixed $value): bool => $value !== null),
            ]);

            $this->applyDecision($application, $interview, $user);
        });

        return $interview;
    }

    private function applyDecision(Application $application, Interview $interview, User $user): void
    {
        if ($interview->decision === null) {
            return;
        }

        $to = match ($interview->decision) {
            InterviewDecision::Proceed => ApplicationStatus::Offer,
            InterviewDecision::Reject => ApplicationStatus::Rejected,
        };

        if ($application->status === $to) {
            return;
        }

        $from = $application->status;
        $application->status = $to;
        $application->save();

        $application->events()->create([
            'user_id' => $user->id,
            'type' => ApplicationEventType::StatusChanged,
            'payload' => ['from' => $from->value, 'to' => $to->value],
        ]);
    }
}
