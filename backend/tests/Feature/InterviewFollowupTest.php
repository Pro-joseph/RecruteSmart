<?php

declare(strict_types=1);

use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\User;
use App\Services\Interviews\InterviewPlanner;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function interviewFollowupSetup(User $owner, OfferService $service): array
{
    $offer = $service->publish($service->create($owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    $application = Application::factory()->create(['offer_id' => $offer->id]);

    $interview = app(InterviewPlanner::class)->plan($application, [
        'type' => 'video',
        'starts_at' => now()->addDays(2)->toIso8601String(),
        'duration_minutes' => 30,
        'location_or_link' => 'https://meet.example.com/x',
        'participants' => [],
    ], $owner);

    return [$application, $interview];
}

it('records a report and closes an interview as done (EF-904)', function (): void {
    [$application, $interview] = interviewFollowupSetup($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/interviews/{$interview->id}", [
            'status' => 'done',
            'notes' => 'Très bon échange technique.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.notes', 'Très bon échange technique.');

    $event = $application->events()->where('type', 'interview_updated')->sole();
    expect($event->payload['status'])->toBe('done')
        ->and($event->payload['interview_id'])->toBe($interview->id);
});

it('moves the application to offer on « proceed » and to rejected on « reject » (UC-04)', function (): void {
    [$application, $interview] = interviewFollowupSetup($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/interviews/{$interview->id}", ['decision' => 'proceed'])
        ->assertOk()
        ->assertJsonPath('data.decision', 'proceed');

    expect($application->fresh()->status->value)->toBe('offer');
    expect($interview->fresh()->status)->toBe(InterviewStatus::Planned);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/interviews/{$interview->id}", ['decision' => 'reject'])
        ->assertOk()
        ->assertJsonPath('data.decision', 'reject');

    expect($application->fresh()->status->value)->toBe('rejected');

    $transitions = $application->events()
        ->where('type', 'status_changed')
        ->pluck('payload')
        ->map(fn (array $payload): string => $payload['to'])
        ->all();

    expect($transitions)->toBe(['interview', 'offer', 'rejected']);
});

it('validates the follow-up payload and blocks other recruiters', function (): void {
    [$application, $interview] = interviewFollowupSetup($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/interviews/{$interview->id}", [
            'status' => 'exploded',
            'decision' => 'maybe',
            'duration_minutes' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'decision', 'duration_minutes']);

    $other = User::factory()->create();
    $this->actingAs($other)
        ->patchJson("/api/v1/interviews/{$interview->id}", ['status' => 'done'])
        ->assertForbidden();

    expect($interview->fresh()->status)->toBe(InterviewStatus::Planned)
        ->and($application->fresh()->status->value)->not->toBe('rejected');
});
