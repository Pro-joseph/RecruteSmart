<?php

declare(strict_types=1);

use App\Mail\CandidateRejectedMail;
use App\Models\Application;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function refuseOfferApplication(User $owner, OfferService $service): Application
{
    $offer = $service->publish($service->create($owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    return Application::factory()->create(['offer_id' => $offer->id]);
}

it('refuses a candidate with an internal reason and no email by default (EF-905)', function (): void {
    Mail::fake();
    $application = refuseOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/reject", [
            'reason' => 'Expérience trop junior pour ce poste.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $event = $application->events()->sole();
    expect($event->type->value)->toBe('status_changed')
        ->and($event->payload)->toBe([
            'from' => 'new',
            'to' => 'rejected',
            'reason' => 'Expérience trop junior pour ce poste.',
        ]);

    Mail::assertNothingQueued();
});

it('queues the refusal email when requested (EF-905)', function (): void {
    Mail::fake();
    $application = refuseOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/reject", [
            'reason' => 'Profil non retenu.',
            'send_email' => true,
            'message' => 'Nous gardons votre profil pour d\'autres offres.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    Mail::assertQueued(CandidateRejectedMail::class, function (CandidateRejectedMail $mail) use ($application): bool {
        return $mail->hasTo($application->email)
            && str_contains($mail->envelope()->subject, $application->offer->title);
    });

    $rendered = (new CandidateRejectedMail($application, 'Nous gardons votre profil.'))->render();
    expect($rendered)->toContain('Nous gardons votre profil.')->toContain($application->full_name);
});

it('validates the refusal payload and blocks other recruiters', function (): void {
    $application = refuseOfferApplication($this->owner, $this->service);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/applications/{$application->id}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $other = User::factory()->create();
    $this->actingAs($other)
        ->postJson("/api/v1/applications/{$application->id}/reject", ['reason' => 'nope'])
        ->assertForbidden();

    expect($application->fresh()->status->value)->toBe('new');
});
