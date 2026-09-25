<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ENF-01: the default candidate list must stay fast on a 5 000-candidate
 * offer — we bound the number of SQL queries (N+1 guard) and the wall time
 * of the first page (with filters applied, as the UI does by default).
 */
it('serves the first page of a 5 000-candidate offer under budget (ENF-01)', function (): void {
    $owner = User::factory()->create();
    $offer = Offer::factory()->create(['user_id' => $owner->id]);

    $now = now();
    $applications = [];
    $analyses = [];

    for ($i = 0; $i < 5000; $i++) {
        $applications[] = [
            'offer_id' => $offer->id,
            'full_name' => 'Candidat '.$i,
            'email' => "c{$i}@example.test",
            'phone' => null,
            'status' => ['new', 'shortlisted', 'interview', 'rejected'][$i % 4],
            'rating' => null,
            'answers' => '[]',
            'cv_path' => "applications/{$offer->id}/cv-{$i}.pdf",
            'cv_original_name' => "cv-{$i}.pdf",
            'cv_mime' => 'application/pdf',
            'cv_size' => 1024,
            'files' => null,
            'consent_at' => $now,
            'consent_version' => 'v1',
            'created_at' => $now->copy()->subMinutes($i),
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($applications, 500) as $chunk) {
        DB::table('applications')->insert($chunk);
    }

    $ids = DB::table('applications')->where('offer_id', $offer->id)->pluck('id');

    foreach ($ids->values() as $index => $id) {
        $analyses[] = [
            'application_id' => $id,
            'status' => 'completed',
            'criteria_version' => 1,
            'prompt_version' => 'v1',
            'match_score' => $index % 101,
            'ats_score' => $index % 101,
            'years_experience' => (float) ($index % 15),
            'city' => 'Paris',
            'analyzed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($analyses, 500) as $chunk) {
        DB::table('application_analyses')->insert($chunk);
    }

    $this->actingAs($owner);

    DB::enableQueryLog();
    $start = microtime(true);

    $response = $this->getJson("/api/v1/offers/{$offer->id}/applications?per_page=25");

    $elapsedMs = (microtime(true) - $start) * 1000;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()->assertJsonPath('meta.total', 5000);

    expect(count($response->json('data')))->toBe(25)
        ->and($queries)->toBeLessThanOrEqual(15)
        ->and($elapsedMs)->toBeLessThan(5000);
})->group('performance');

it('keeps filtered searches on a 5 000-candidate offer bounded (ENF-01)', function (): void {
    $owner = User::factory()->create();
    $offer = Offer::factory()->create(['user_id' => $owner->id]);

    $now = now();
    $rows = [];

    for ($i = 0; $i < 5000; $i++) {
        $rows[] = [
            'offer_id' => $offer->id,
            'full_name' => 'Candidat '.$i,
            'email' => "c{$i}@example.test",
            'phone' => null,
            'status' => 'new',
            'rating' => null,
            'answers' => '[]',
            'cv_path' => "applications/{$offer->id}/cv-{$i}.pdf",
            'cv_original_name' => "cv-{$i}.pdf",
            'cv_mime' => 'application/pdf',
            'cv_size' => 1024,
            'files' => null,
            'consent_at' => $now,
            'consent_version' => 'v1',
            'created_at' => $now->copy()->subMinutes($i),
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('applications')->insert($chunk);
    }

    $ids = DB::table('applications')->where('offer_id', $offer->id)->pluck('id');

    $analysisRows = $ids->values()->map(fn ($id, $index) => [
        'application_id' => $id,
        'status' => 'completed',
        'criteria_version' => 1,
        'prompt_version' => 'v1',
        'match_score' => $index % 101,
        'ats_score' => $index % 101,
        'years_experience' => (float) ($index % 15),
        'city' => 'Paris',
        'analyzed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all();

    foreach (array_chunk($analysisRows, 500) as $chunk) {
        DB::table('application_analyses')->insert($chunk);
    }

    $this->actingAs($owner);

    DB::enableQueryLog();
    $start = microtime(true);

    $response = $this->getJson("/api/v1/offers/{$offer->id}/applications?per_page=25&min_score=60&sort=-match_score");

    $elapsedMs = (microtime(true) - $start) * 1000;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();

    expect($queries)->toBeLessThanOrEqual(15)
        ->and($elapsedMs)->toBeLessThan(5000);
})->group('performance');
