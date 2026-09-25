<?php

declare(strict_types=1);

use App\Jobs\AnalyzeApplicationJob;
use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\User;
use App\Services\Analysis\Ats\AtsScorer;
use App\Services\Analysis\CvAnalyzer;
use App\Services\Analysis\CvTextExtractor;
use App\Services\Analysis\CvTextSanitizer;
use App\Services\Analysis\PromptInjectionDetector;
use App\Services\Analysis\ScoreCalculator;
use App\Services\Analysis\SkillNormalizer;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

function privacySubmittedApplication(): Application
{
    $text = 'Jane Doe - Developpeuse PHP Laravel. Contact jane.doe@example.com tel +212 600 000 000 site http://janedoe.dev. '
        .str_repeat('Experience en API REST et en conception de services robustes. ', 4);

    $cv = UploadedFile::fake()->createWithContent('cv.pdf', file_get_contents(extractorTestPdf($text)));

    $offer = test()->service->publish(test()->service->create(test()->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
        'required_skills' => ['laravel', 'php'],
    ]));

    test()->postJson("/api/v1/public/offers/{$offer->public_token}/applications", [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'consent' => '1',
        'files' => ['cv' => $cv],
    ])->assertCreated();

    return $offer->applications()->firstOrFail();
}

function privacyValidPayload(): array
{
    return [
        'years_experience_total' => 4.0,
        'location' => ['city' => 'Casablanca', 'country' => 'Maroc'],
        'skills' => ['php', 'laravel'],
        'languages' => [['name' => 'français', 'level' => 'courant']],
        'education' => [['degree' => 'Licence', 'field' => 'Informatique', 'institution' => 'Université', 'year' => 2020]],
        'recent_positions' => [['title' => 'Développeuse backend', 'company' => 'ACME', 'months' => 24]],
        'criteria' => ['required_skills' => ['score' => 85, 'evidence' => 'OK', 'matched' => ['laravel', 'php'], 'missing' => []]],
        'summary' => 'Profil backend solide.',
        'strengths' => ['Laravel'],
        'gaps' => ['Redis'],
        'anomalies' => [],
    ];
}

it('sends only sanitized CV content to the LLM provider (RG-07)', function (): void {
    config()->set('llm.api_key', 'test-key');
    config()->set('llm.model', 'llama-test');
    config()->set('llm.structured_output', false);

    Http::fake(['*' => [
        'model' => 'llama-test',
        'choices' => [['message' => ['content' => json_encode(privacyValidPayload(), JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ]]);

    $application = privacySubmittedApplication();

    (new AnalyzeApplicationJob($application->id))->handle(
        app(CvTextExtractor::class),
        app(AtsScorer::class),
        app(CvTextSanitizer::class),
        app(PromptInjectionDetector::class),
        app(ScoreCalculator::class),
        app(SkillNormalizer::class),
        app(CvAnalyzer::class),
    );

    expect(ApplicationAnalysis::where('application_id', $application->id)->firstOrFail()->status->value)
        ->toBe('completed');

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return ! str_contains($body, 'jane.doe@example.com')
            && ! str_contains($body, '+212 600 000 000')
            && ! str_contains($body, 'janedoe.dev')
            && ! str_contains($body, 'Jane Doe')
            && str_contains($body, '[EMAIL]')
            && str_contains($body, '[CANDIDAT]');
    });
});

it('follows the spec job settings (queue, tries, timeout, backoff)', function (): void {
    $job = new AnalyzeApplicationJob(1);

    expect($job->queue)->toBe('analysis')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(180)
        ->and($job->backoff)->toBe([30, 120, 600]);
});
