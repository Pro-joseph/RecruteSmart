<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\ApplicationEvent;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function ficheApplication(User $owner, OfferService $service): Application
{
    $offer = $service->publish($service->create($owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    return Application::factory()->create([
        'offer_id' => $offer->id,
        'answers' => ['city' => 'Rabat'],
    ]);
}

it('returns the fiche with answers, files and the full analysis (EF-701/702)', function (): void {
    $application = ficheApplication($this->owner, $this->service);
    ApplicationAnalysis::factory()->completed()->create(['application_id' => $application->id]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/applications/{$application->id}")
        ->assertOk();

    $data = $response->json('data');

    expect($data['id'])->toBe($application->id)
        ->and($data['offer']['title'])->toBe('Dev Laravel')
        ->and($data['status'])->toBe('new')
        ->and($data['analysis']['status'])->toBe('completed')
        ->and($data['analysis']['match_score'])->toBe(75)
        ->and($data['analysis']['ats_verdict'])->toBe('compliant')
        ->and($data['analysis']['summary'])->toBe('Profil pertinent.')
        ->and($data['analysis']['is_stale'])->toBeFalse();

    $names = array_column($data['answers'], 'key');
    expect($names)->toContain('full_name', 'email', 'city');

    $nameRow = collect($data['answers'])->firstWhere('key', 'full_name');
    expect($nameRow['value'])->toBe($application->full_name)
        ->and($nameRow['label'])->not->toBe('');

    expect($data['files'][0]['key'])->toBe('cv')
        ->and($data['files'][0]['url'])->toBe("/api/v1/applications/{$application->id}/files/cv");
});

it('flags a stale analysis on the fiche after criteria change (RG-09)', function (): void {
    $application = ficheApplication($this->owner, $this->service);
    ApplicationAnalysis::factory()->completed()->create(['application_id' => $application->id]);

    $this->service->update($application->offer, ['required_skills' => ['go']]);

    $this->actingAs($this->owner)
        ->getJson("/api/v1/applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('data.analysis.is_stale', true);
});

it('returns the history newest first with the acting user (EF-705)', function (): void {
    $application = ficheApplication($this->owner, $this->service);

    ApplicationEvent::factory()->create([
        'application_id' => $application->id,
        'user_id' => $this->owner->id,
        'type' => 'status_changed',
        'payload' => ['from' => 'new', 'to' => 'shortlisted'],
        'created_at' => now()->subMinutes(5),
    ]);
    ApplicationEvent::factory()->create([
        'application_id' => $application->id,
        'user_id' => $this->owner->id,
        'type' => 'analysis_completed',
        'payload' => ['match_score' => 82],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/applications/{$application->id}/events")
        ->assertOk();

    $events = $response->json('data');
    expect($events)->toHaveCount(2)
        ->and($events[0]['type'])->toBe('analysis_completed')
        ->and($events[1]['type'])->toBe('status_changed')
        ->and($events[1]['payload']['to'])->toBe('shortlisted')
        ->and($events[0]['user']['email'])->toBe($this->owner->email);
});

it('forbids fiche and history access to other recruiters', function (): void {
    $application = ficheApplication($this->owner, $this->service);
    $other = User::factory()->create();

    $this->actingAs($other)->getJson("/api/v1/applications/{$application->id}")->assertForbidden();
    $this->actingAs($other)->getJson("/api/v1/applications/{$application->id}/events")->assertForbidden();
});
