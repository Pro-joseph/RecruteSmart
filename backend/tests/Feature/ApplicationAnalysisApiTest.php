<?php

declare(strict_types=1);

use App\Jobs\AnalyzeApplicationJob;
use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Offer;
use App\Models\User;
use App\Services\Analysis\Ats\AtsScorer;
use App\Services\Analysis\CvAnalyzer;
use App\Services\Analysis\CvTextExtractor;
use App\Services\Analysis\CvTextSanitizer;
use App\Services\Analysis\FakeCvAnalyzer;
use App\Services\Analysis\PromptInjectionDetector;
use App\Services\Analysis\ScoreCalculator;
use App\Services\Analysis\SkillNormalizer;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('private');
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function apiPublishedOffer(array $extra = []): Offer
{
    $offer = test()->service->create(test()->owner, array_merge([
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
        'required_skills' => ['laravel', 'php'],
    ], $extra));

    return test()->service->publish($offer);
}

function apiSubmittedApplication(Offer $offer): Application
{
    $text = 'Jane Doe - Developpeuse PHP Laravel. '.str_repeat('Je conçois des applications web robustes en API REST. ', 5);
    $cv = UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(extractorTestPdf($text)));

    test()->postJson("/api/v1/public/offers/{$offer->public_token}/applications", [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'consent' => '1',
        'files' => ['cv' => $cv],
    ])->assertCreated();

    return $offer->applications()->firstOrFail();
}

function apiRunAnalysis(int $applicationId): void
{
    (new AnalyzeApplicationJob($applicationId))->handle(
        app(CvTextExtractor::class),
        app(AtsScorer::class),
        app(CvTextSanitizer::class),
        app(PromptInjectionDetector::class),
        app(ScoreCalculator::class),
        app(SkillNormalizer::class),
        app(CvAnalyzer::class),
    );
}

it('embeds the analysis block in the applications list', function (): void {
    $offer = apiPublishedOffer();
    $application = apiSubmittedApplication($offer);

    $this->app->instance(CvAnalyzer::class, new FakeCvAnalyzer);
    apiRunAnalysis($application->id);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$offer->id}/applications")
        ->assertOk();

    $analysis = $response->json('data.0.analysis');

    expect($analysis['status'])->toBe('completed')
        ->and($analysis['match_score'])->toBeInt()
        ->and($analysis['ats_score'])->toBeInt()
        ->and($analysis['ats_verdict'])->not->toBeNull()
        ->and($analysis['skills'])->toBe(['laravel', 'php'])
        ->and($analysis['is_stale'])->toBeFalse()
        ->and($analysis['error_code'])->toBeNull()
        ->and($analysis['city'])->toBe('Casablanca');
});

it('reports a pending analysis without scores', function (): void {
    $offer = apiPublishedOffer();
    apiSubmittedApplication($offer);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$offer->id}/applications")
        ->assertOk();

    $analysis = $response->json('data.0.analysis');

    expect($analysis['status'])->toBe('pending')
        ->and($analysis['match_score'])->toBeNull()
        ->and($analysis['is_stale'])->toBeFalse()
        ->and($analysis['skills'])->toBe([]);
});

it('marks analyses as stale when the offer criteria changed', function (): void {
    $offer = apiPublishedOffer();
    apiSubmittedApplication($offer);

    $this->service->update($offer, ['required_skills' => ['go', 'sql']]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$offer->id}/applications")
        ->assertOk();

    expect($response->json('data.0.analysis.is_stale'))->toBeTrue();
});

it('relaunches a single analysis for the owner only', function (): void {
    $offer = apiPublishedOffer();
    $application = Application::factory()->create(['offer_id' => $offer->id]);
    ApplicationAnalysis::factory()->create(['application_id' => $application->id]);

    $this->actingAs(User::factory()->create());
    $this->postJson("/api/v1/applications/{$application->id}/reanalyze")->assertForbidden();

    $this->actingAs($this->owner);
    $this->postJson("/api/v1/applications/{$application->id}/reanalyze")
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued');

    $this->assertDatabaseHas('jobs', ['queue' => 'analysis']);
});

it('queues only stale analyses from the offer reanalyze endpoint', function (): void {
    $offer = apiPublishedOffer();
    Application::factory()->create(['offer_id' => $offer->id])
        ->analysis()->create(['status' => 'completed', 'criteria_version' => 1]);

    $this->actingAs($this->owner);
    $this->postJson("/api/v1/offers/{$offer->id}/reanalyze")
        ->assertAccepted()
        ->assertJsonPath('data.queued', 0);
    $this->assertDatabaseCount('jobs', 0);

    $this->service->update($offer, ['required_skills' => ['go']]);

    $this->postJson("/api/v1/offers/{$offer->id}/reanalyze")
        ->assertAccepted()
        ->assertJsonPath('data.queued', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'analysis']);

    $this->actingAs(User::factory()->create());
    $this->postJson("/api/v1/offers/{$offer->id}/reanalyze")->assertForbidden();
});
