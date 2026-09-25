<?php

declare(strict_types=1);

use App\Enums\AnalysisStatus;
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
use App\Services\Analysis\ProviderException;
use App\Services\Analysis\ScoreCalculator;
use App\Services\Analysis\SkillNormalizer;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('private');
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function analysisOffer(array $extra = []): Offer
{
    $offer = test()->service->create(test()->owner, array_merge([
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
        'required_skills' => ['laravel', 'php'],
        'preferred_skills' => ['docker'],
        'min_experience_years' => 3,
        'education_level' => 'bac+3',
        'languages' => ['français'],
    ], $extra));

    return test()->service->publish($offer);
}

function analysisCvPdf(): UploadedFile
{
    $text = 'Jane Doe - Developpeuse PHP Laravel. '.str_repeat('Je conçois des applications web robustes en API REST. ', 5);

    return UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(extractorTestPdf($text)));
}

function submitAnalysisApplication(Offer $offer): Application
{
    test()->postJson("/api/v1/public/offers/{$offer->public_token}/applications", [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'consent' => '1',
        'files' => ['cv' => analysisCvPdf()],
    ])->assertCreated();

    return $offer->applications()->firstOrFail();
}

function runApplicationAnalysis(int $applicationId): AnalyzeApplicationJob
{
    $job = new AnalyzeApplicationJob($applicationId);
    $job->handle(
        app(CvTextExtractor::class),
        app(AtsScorer::class),
        app(CvTextSanitizer::class),
        app(PromptInjectionDetector::class),
        app(ScoreCalculator::class),
        app(SkillNormalizer::class),
        app(CvAnalyzer::class),
    );

    return $job;
}

function jobValidPayload(): array
{
    return [
        'years_experience_total' => 4.0,
        'location' => ['city' => 'Casablanca', 'country' => 'Maroc'],
        'skills' => ['php', 'laravel', 'docker'],
        'languages' => [['name' => 'français', 'level' => 'courant']],
        'education' => [['degree' => 'Licence', 'field' => 'Informatique', 'institution' => 'Université', 'year' => 2020]],
        'recent_positions' => [['title' => 'Développeuse backend', 'company' => 'ACME', 'months' => 24]],
        'criteria' => ['experience' => ['score' => 70, 'evidence' => 'Parcours cohérent.']],
        'summary' => 'Profil backend solide.',
        'strengths' => ['Laravel'],
        'gaps' => ['Redis'],
        'anomalies' => [],
    ];
}

function jobLlmResponse(array $payload): array
{
    return [
        'model' => 'llama-3.3-70b-versatile',
        'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ];
}

function configureLlmForTest(): void
{
    config()->set('llm.api_key', 'test-key');
    config()->set('llm.model', 'llama-test');
    config()->set('llm.structured_output', false);
}

it('creates a pending analysis and pushes the job on submit, then completes the pipeline', function (): void {
    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);

    $this->assertDatabaseHas('application_analyses', [
        'application_id' => $application->id,
        'status' => 'pending',
        'criteria_version' => 1,
        'prompt_version' => 'v1',
    ]);
    $this->assertDatabaseHas('jobs', ['queue' => 'analysis']);

    $this->app->instance(CvAnalyzer::class, new FakeCvAnalyzer);
    runApplicationAnalysis($application->id);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis->match_score)->not->toBeNull()
        ->and($analysis->match_breakdown)->not->toBeNull()
        ->and($analysis->ats_score)->not->toBeNull()
        ->and($analysis->ats_checks)->toHaveCount(6)
        ->and($analysis->summary)->toBe('Profil backend solide, à creuser sur l\'infrastructure.')
        ->and($analysis->llm_provider)->toBe('fake')
        ->and($analysis->tokens_in)->toBe(100)
        ->and($analysis->input_hash)->toHaveLength(64)
        ->and($analysis->analyzed_at)->not->toBeNull()
        ->and($analysis->error_code)->toBeNull()
        ->and($analysis->city)->toBe('Casablanca')
        ->and($analysis->city_normalized)->toBe('casablanca')
        ->and($analysis->years_experience)->toBe('4.0');

    expect($application->fresh()->skills()->pluck('slug')->sort()->values()->all())
        ->toBe(['laravel', 'php']);
});

it('marks unreadable CVs as failed without calling the LLM', function (): void {
    Http::fake();
    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);
    $application->forceFill(['cv_mime' => 'application/octet-stream'])->save();

    runApplicationAnalysis($application->id);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error_code)->toBe('unreadable_cv');
    Http::assertNothingSent();
});

it('produces ATS only (RG-08) when the offer has no criteria', function (): void {
    $offer = analysisOffer([
        'required_skills' => [],
        'preferred_skills' => [],
        'min_experience_years' => null,
        'education_level' => null,
        'languages' => [],
    ]);
    $application = submitAnalysisApplication($offer);

    $this->app->instance(CvAnalyzer::class, new FakeCvAnalyzer);
    runApplicationAnalysis($application->id);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis->match_score)->toBeNull()
        ->and($analysis->match_breakdown)->toBeNull()
        ->and($analysis->ats_score)->not->toBeNull()
        ->and($analysis->summary)->not->toBeNull();
});

it('completes without an LLM call when the extracted text is too short', function (): void {
    Http::fake();
    $offer = analysisOffer();

    $shortPdf = extractorTestPdf('CV trop court.');
    // submit with short-text PDF
    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", [
        'full_name' => 'Jean Court',
        'email' => 'court@example.com',
        'consent' => '1',
        'files' => ['cv' => UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents($shortPdf))],
    ])->assertCreated();
    @unlink($shortPdf);
    $application = $offer->applications()->where('email', 'court@example.com')->firstOrFail();

    runApplicationAnalysis($application->id);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis->match_score)->toBeNull()
        ->and($analysis->ats_score)->not->toBeNull()
        ->and($analysis->anomalies)->toContain('texte_non_extractible');
    Http::assertNothingSent();
});

it('fails as invalid_output after the LLM retries twice', function (): void {
    configureLlmForTest();
    $invalid = jobValidPayload();
    $invalid['skills'] = 'not-an-array';
    Http::fake(['*' => Http::sequence()
        ->push(jobLlmResponse($invalid))
        ->push(jobLlmResponse($invalid))]);

    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);

    runApplicationAnalysis($application->id);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error_code)->toBe('invalid_output');
    Http::assertSentCount(2);
});

it('propagates transient provider errors for job retries and records them in failed()', function (): void {
    configureLlmForTest();
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28')]);

    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);

    $caught = null;
    try {
        runApplicationAnalysis($application->id);
    } catch (ProviderException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(ProviderException::class);
    if (! $caught instanceof ProviderException) {
        return;
    }

    expect($caught->errorCode)->toBe('provider_timeout');

    (new AnalyzeApplicationJob($application->id))->failed($caught);

    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error_code)->toBe('provider_timeout');
});

it('stops at the daily recruiter quota without calling the LLM', function (): void {
    config()->set('llm.daily_limit_per_user', 1);
    Http::fake(['*' => jobLlmResponse(jobValidPayload())]);
    configureLlmForTest();

    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);

    runApplicationAnalysis($application->id);
    expect(ApplicationAnalysis::where('application_id', $application->id)->firstOrFail()->status)
        ->toBe(AnalysisStatus::Completed);

    runApplicationAnalysis($application->id);
    $analysis = ApplicationAnalysis::where('application_id', $application->id)->firstOrFail();

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error_code)->toBe('quota_exceeded');
    Http::assertSentCount(1);
});

it('reuses the previous result when the inputs are unchanged', function (): void {
    configureLlmForTest();
    Http::fake(['*' => jobLlmResponse(jobValidPayload())]);

    $offer = analysisOffer();
    $application = submitAnalysisApplication($offer);

    runApplicationAnalysis($application->id);
    runApplicationAnalysis($application->id);

    Http::assertSentCount(1);
    expect(ApplicationAnalysis::where('application_id', $application->id)->firstOrFail()->status)
        ->toBe(AnalysisStatus::Completed);
});
