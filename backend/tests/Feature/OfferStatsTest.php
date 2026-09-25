<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->offer = Offer::factory()->create(['user_id' => $this->owner->id]);
});

it('returns totals, status split, averages and score buckets (EF-508)', function (): void {
    $scores = [90, 70, 50, 20];

    foreach ($scores as $index => $score) {
        $application = Application::factory()->create([
            'offer_id' => $this->offer->id,
            'status' => $index === 0 ? 'hired' : 'new',
        ]);
        ApplicationAnalysis::factory()->create([
            'application_id' => $application->id,
            'status' => 'completed',
            'match_score' => $score,
            'ats_score' => $score,
        ]);
    }

    Application::factory()->create(['offer_id' => $this->offer->id]);

    $response = $this->actingAs($this->owner)
        ->get("/api/v1/offers/{$this->offer->id}/stats");

    $response->assertOk();

    $data = $response->json('data');

    expect($data['applications_count'])->toBe(5)
        ->and($data['by_status']['new'])->toBe(4)
        ->and($data['by_status']['hired'])->toBe(1)
        ->and($data['by_status']['rejected'])->toBe(0)
        ->and($data['avg_match_score'])->toBe(58)
        ->and($data['avg_ats_score'])->toBe(58)
        ->and($data['score_buckets']['80_100'])->toBe(1)
        ->and($data['score_buckets']['60_79'])->toBe(1)
        ->and($data['score_buckets']['40_59'])->toBe(1)
        ->and($data['score_buckets']['0_39'])->toBe(1)
        ->and($data['score_buckets']['unscored'])->toBe(1);
});

it('refuses stats to another recruiter (EF-508)', function (): void {
    $this->actingAs(User::factory()->create())
        ->get("/api/v1/offers/{$this->offer->id}/stats")
        ->assertForbidden();
});
