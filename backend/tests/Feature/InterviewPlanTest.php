<?php

declare(strict_types=1);

use App\Enums\InterviewStatus;
use App\Mail\InterviewInvitationMail;
use App\Models\Application;
use App\Models\User;
use App\Services\Interviews\IcsCalendar;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function interviewPlanApplication(User $owner, OfferService $service): Application
{
    $offer = $service->publish($service->create($owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    return Application::factory()->create(['offer_id' => $offer->id]);
}

it('plans an interview, moves the candidate to interview and queues the .ics invite (EF-902/903)', function (): void {
    Mail::fake();
    $application = interviewPlanApplication($this->owner, $this->service);

    $startsAt = now()->addDays(3)->startOfHour()->toIso8601String();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/interviews", [
            'type' => 'video',
            'starts_at' => $startsAt,
            'duration_minutes' => 45,
            'location_or_link' => 'https://meet.example.com/x',
            'participants' => [$this->owner->email],
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.status', 'planned')
        ->assertJsonPath('data.duration_minutes', 45);

    expect($application->fresh()->status->value)->toBe('interview');

    $interview = $application->interviews()->sole();
    expect($interview->invitation_sent_at)->not->toBeNull()
        ->and($interview->participants)->toBe([$this->owner->email]);

    $types = $application->events()->orderBy('id')->pluck('type')->map(fn ($t) => $t->value)->all();
    expect($types)->toBe(['status_changed', 'interview_planned']);

    Mail::assertQueued(InterviewInvitationMail::class, function (InterviewInvitationMail $mail) use ($application): bool {
        return $mail->hasTo($application->email)
            && str_contains($mail->envelope()->subject, 'Invitation à un entretien');
    });
});

it('builds a valid .ics calendar attachment (EF-903)', function (): void {
    $application = interviewPlanApplication($this->owner, $this->service);
    $interview = $application->interviews()->create([
        'type' => 'onsite',
        'starts_at' => now()->addDay()->setTime(14, 30),
        'duration_minutes' => 60,
        'location_or_link' => '12 rue des Fleurs',
        'participants' => [],
        'status' => InterviewStatus::Planned,
        'created_by' => $this->owner->id,
    ]);

    $ics = app(IcsCalendar::class)->build($interview);

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('METHOD:REQUEST')
        ->toContain('UID:interview-'.$interview->id.'@recrutesmart.local')
        ->toContain('LOCATION:12 rue des Fleurs')
        ->toContain('ORGANIZER;CN=')
        ->toContain('END:VCALENDAR')
        ->and(substr_count($ics, "\r\n"))->toBeGreaterThan(5);

    $rendered = (new InterviewInvitationMail($interview))->render();
    expect($rendered)->toContain('entretien')->toContain($application->full_name);
});

it('validates the interview payload and blocks other recruiters', function (): void {
    $application = interviewPlanApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/interviews", [
            'type' => 'carrier-pigeon',
            'starts_at' => now()->subDay()->toIso8601String(),
            'duration_minutes' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'starts_at', 'duration_minutes']);

    $other = User::factory()->create();
    $this->actingAs($other)
        ->postJson("/api/v1/applications/{$application->id}/interviews", [
            'type' => 'phone',
            'starts_at' => now()->addDay()->toIso8601String(),
            'duration_minutes' => 30,
        ])
        ->assertForbidden();

    expect($application->interviews()->count())->toBe(0);
});
