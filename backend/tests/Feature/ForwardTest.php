<?php

declare(strict_types=1);

use App\Enums\ForwardStatus;
use App\Jobs\SendForwardJob;
use App\Mail\CandidatesForwardedMail;
use App\Models\Application;
use App\Models\Forward;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
    $offer = $this->service->publish($this->service->create($this->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));
    $this->offer = $offer;
    $this->applications = Application::factory()->count(3)->create(['offer_id' => $offer->id]);
});

it('creates a forward from selected candidates and queues the delivery (EF-1001→1004)', function (): void {
    Queue::fake();

    $ids = $this->applications->pluck('id')->all();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/forwards', [
            'to_emails' => ['client@example.com'],
            'subject' => 'Candidatures Dev Laravel',
            'message' => 'Bonjour, en pièce jointe.',
            'application_ids' => $ids,
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.candidates.0.name', $this->applications[0]->full_name);

    $forward = Forward::sole();
    expect($forward->user_id)->toBe($this->owner->id)
        ->and($forward->applications()->count())->toBe(3);

    $events = Application::find($ids[0])->events()->where('type', 'forward_created')->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->payload['forward_id'])->toBe($forward->id);

    Queue::assertPushed(SendForwardJob::class, fn (SendForwardJob $job): bool => $job->forwardId === $forward->id);
});

it('sends one email with every candidate and records forwarded events (spec §7)', function (): void {
    Mail::fake();

    $forward = Forward::factory()->create(['user_id' => $this->owner->id, 'include_analysis' => true]);
    foreach ($this->applications as $application) {
        $forward->applications()->attach($application->id, [
            'candidate_name_snapshot' => $application->full_name,
        ]);
    }

    (new SendForwardJob($forward->id))->handle();

    Mail::assertSent(CandidatesForwardedMail::class, function (CandidatesForwardedMail $mail): bool {
        return $mail->hasTo('client@example.com')
            && count($mail->candidates) === 3
            && str_contains($mail->candidates[0]['name'], ' ');
    });

    $fresh = $forward->fresh();
    expect($fresh->status)->toBe(ForwardStatus::Sent)
        ->and($fresh->sent_at)->not->toBeNull();

    foreach ($this->applications as $application) {
        expect($application->events()->where('type', 'forwarded')->count())->toBe(1);
    }
});

it('enforces the recipient and candidate limits (spec §7 anti-abus)', function (): void {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/forwards', [
            'to_emails' => array_map(static fn (int $i): string => "u{$i}@example.com", range(1, 11)),
            'subject' => 'x',
            'application_ids' => $this->applications->pluck('id')->all(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_emails']);

    $many = Application::factory()->count(51)->create(['offer_id' => $this->offer->id]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/forwards', [
            'to_emails' => ['client@example.com'],
            'subject' => 'x',
            'application_ids' => $many->pluck('id')->all(),
        ])
        ->assertStatus(422);
});

it('supports select-all with filters and lists the transfer history (EF-1002, EF-1007)', function (): void {
    Queue::fake();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/forwards', [
            'to_emails' => ['client@example.com'],
            'subject' => 'Tous les profils',
            'offer_id' => $this->offer->id,
            'select_all' => true,
            'filters' => ['status' => ['new']],
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.candidates', fn ($candidates): bool => count($candidates) === 3);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/forwards')
        ->assertOk()
        ->assertJsonPath('data.0.subject', 'Tous les profils');

    $other = User::factory()->create();
    $forward = Forward::sole();

    $this->actingAs($other)->getJson("/api/v1/forwards/{$forward->id}")->assertForbidden();
    $this->actingAs($this->owner)->getJson("/api/v1/forwards/{$forward->id}")->assertOk();
});

it('uses signed 7-day links instead of attachments above the size limit (EF-1005)', function (): void {
    Mail::fake();

    $big = Application::factory()->create([
        'offer_id' => $this->offer->id,
        'cv_size' => 16 * 1024 * 1024,
    ]);

    $forward = Forward::factory()->create(['user_id' => $this->owner->id]);
    $forward->applications()->attach($big->id, ['candidate_name_snapshot' => $big->full_name]);

    (new SendForwardJob($forward->id))->handle();

    Mail::assertSent(CandidatesForwardedMail::class, function (CandidatesForwardedMail $mail): bool {
        foreach ($mail->candidates as $candidate) {
            if (isset($candidate['link']) && str_contains($candidate['link'], 'signature=')) {
                return true;
            }
        }

        return false;
    });

    expect($forward->fresh()->delivery->value)->toBe('links');
});
