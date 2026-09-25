<?php

declare(strict_types=1);

namespace App\Services\Interviews;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Mail\InterviewInvitationMail;
use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InterviewPlanner
{
    /**
     * Plans an interview (EF-902), moves the application to « interview »,
     * records the history entries and queues the .ics invitation (EF-903).
     *
     * @param array{
     *     type: string,
     *     starts_at: string,
     *     duration_minutes: int,
     *     location_or_link?: string|null,
     *     participants?: list<string>
     * } $data
     */
    public function plan(Application $application, array $data, User $user): Interview
    {
        $application->loadMissing('offer');

        $interview = DB::transaction(function () use ($application, $data, $user): Interview {
            /** @var Interview $interview */
            $interview = $application->interviews()->create([
                'type' => $data['type'],
                'starts_at' => CarbonImmutable::parse($data['starts_at']),
                'duration_minutes' => $data['duration_minutes'],
                'location_or_link' => $data['location_or_link'] ?? null,
                'participants' => $data['participants'] ?? [],
                'status' => InterviewStatus::Planned,
                'created_by' => $user->id,
            ]);

            if ($application->status !== ApplicationStatus::Interview) {
                $from = $application->status;
                $application->status = ApplicationStatus::Interview;
                $application->save();

                $application->events()->create([
                    'user_id' => $user->id,
                    'type' => ApplicationEventType::StatusChanged,
                    'payload' => ['from' => $from->value, 'to' => ApplicationStatus::Interview->value],
                ]);
            }

            $application->events()->create([
                'user_id' => $user->id,
                'type' => ApplicationEventType::InterviewPlanned,
                'payload' => [
                    'interview_id' => $interview->id,
                    'type' => $interview->type->value,
                    'starts_at' => $interview->starts_at->toIso8601String(),
                ],
            ]);

            return $interview;
        });

        Mail::to($application->email)->queue(new InterviewInvitationMail($interview));

        $interview->forceFill(['invitation_sent_at' => now()])->save();

        return $interview;
    }
}
