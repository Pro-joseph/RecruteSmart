<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function statusOfferApplication(User $owner, OfferService $service): Application
{
    $offer = $service->publish($service->create($owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    return Application::factory()->create(['offer_id' => $offer->id]);
}

it('changes the status and records a status_changed event (EF-703)', function (): void {
    $application = statusOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'shortlisted'])
        ->assertOk()
        ->assertJsonPath('data.status', 'shortlisted');

    expect($application->fresh()->status->value)->toBe('shortlisted');

    $event = $application->events()->sole();
    expect($event->type->value)->toBe('status_changed')
        ->and($event->payload)->toBe(['from' => 'new', 'to' => 'shortlisted'])
        ->and($event->user_id)->toBe($this->owner->id);
});

it('skips the event when the status is unchanged', function (): void {
    $application = statusOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'new'])
        ->assertOk()
        ->assertJsonPath('data.status', 'new');

    expect($application->events()->count())->toBe(0);
});

it('rejects unknown statuses and other recruiters', function (): void {
    $application = statusOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'zombie'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs(User::factory()->create())
        ->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'rejected'])
        ->assertForbidden();
});

it('bulk-updates only the applications of the target offer (EF-901 groundwork)', function (): void {
    $first = statusOfferApplication($this->owner, $this->service);
    $second = Application::factory()->create(['offer_id' => $first->offer_id]);
    $foreign = statusOfferApplication(User::factory()->create(), $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/offers/{$first->offer_id}/applications/bulk-status", [
            'application_ids' => [$first->id, $second->id, $foreign->id],
            'status' => 'shortlisted',
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect($first->fresh()->status->value)->toBe('shortlisted')
        ->and($second->fresh()->status->value)->toBe('shortlisted')
        ->and($foreign->fresh()->status->value)->toBe('new')
        ->and($first->events()->count())->toBe(1)
        ->and($second->events()->count())->toBe(1)
        ->and($foreign->events()->count())->toBe(0);
});

it('stores notes and ratings with history entries (EF-704/705)', function (): void {
    $application = statusOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/notes", [
            'note' => 'Très bon profil backend.',
            'rating' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.rating', 4);

    expect($application->fresh()->rating)->toBe(4);

    $event = $application->events()->sole();
    expect($event->type->value)->toBe('note_added')
        ->and($event->payload)->toBe(['note' => 'Très bon profil backend.', 'rating' => 4]);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/notes", ['rating' => 5])
        ->assertOk()
        ->assertJsonPath('data.rating', 5);

    expect($application->events()->count())->toBe(2)
        ->and($application->events()->latest('id')->first()->payload)->toBe(['rating' => 5]);
});

it('validates note payloads and forbids other recruiters', function (): void {
    $application = statusOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/notes", [])
        ->assertUnprocessable();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/notes", ['rating' => 6])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);

    $this->actingAs(User::factory()->create())
        ->postJson("/api/v1/applications/{$application->id}/notes", ['note' => 'nope'])
        ->assertForbidden();
});
