<?php

declare(strict_types=1);

use App\Services\Analysis\AnalysisSchemaValidator;
use App\Services\Analysis\FakeCvAnalyzer;
use App\Services\Analysis\InvalidAnalysisOutputException;
use App\Services\Analysis\LlmCvAnalyzer;
use App\Services\Analysis\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function validLlmPayload(array $overrides = []): array
{
    return array_merge([
        'years_experience_total' => 3.5,
        'location' => ['city' => 'Casablanca', 'country' => 'Maroc'],
        'skills' => ['php', 'laravel'],
        'languages' => [['name' => 'français', 'level' => 'courant']],
        'education' => [['degree' => 'Licence', 'field' => 'Informatique', 'institution' => 'Université X', 'year' => 2021]],
        'recent_positions' => [['title' => 'Développeur backend', 'company' => 'Société Y', 'months' => 18]],
        'criteria' => ['experience' => ['score' => 70, 'evidence' => 'Parcours cohérent.']],
        'summary' => 'Profil backend de 3,5 ans.',
        'strengths' => ['Laravel'],
        'gaps' => ['Redis'],
    ], $overrides);
}

function llmResponse(array $payload): array
{
    return [
        'model' => 'llama-3.3-70b-versatile',
        'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ];
}

function makeLlmAnalyzer(): LlmCvAnalyzer
{
    config()->set('llm.api_key', 'test-key');
    config()->set('llm.model', 'llama-test');
    config()->set('llm.structured_output', false);

    return app(LlmCvAnalyzer::class);
}

it('validates payloads against the schema', function (): void {
    $validator = new AnalysisSchemaValidator;

    expect($validator->validate(validLlmPayload()))->toBe([]);

    expect($validator->validate(validLlmPayload(['criteria' => ['experience' => ['score' => 150, 'evidence' => 'x']]])))
        ->not->toBe([]);

    expect($validator->validate(validLlmPayload(['summary' => 123])))->not->toBe([]);
});

it('returns a validated payload with token accounting', function (): void {
    Http::fake(['*' => llmResponse(validLlmPayload())]);

    $result = makeLlmAnalyzer()->analyze('Texte du CV', ['title' => 'Dev Laravel']);

    expect($result->payload['summary'])->toBe('Profil backend de 3,5 ans.')
        ->and($result->tokensIn)->toBe(100)
        ->and($result->tokensOut)->toBe(50)
        ->and($result->model)->toBe('llama-3.3-70b-versatile')
        ->and($result->provider)->toBe('groq');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['response_format']['type'] === 'json_object');
});

it('retries once with validation feedback', function (): void {
    $invalid = validLlmPayload(['criteria' => ['experience' => ['score' => 150, 'evidence' => 'x']]]);
    Http::fake([
        '*' => Http::sequence()
            ->push(llmResponse($invalid))
            ->push(llmResponse(validLlmPayload())),
    ]);

    $result = makeLlmAnalyzer()->analyze('CV', ['title' => 'Dev']);

    expect($result->payload['summary'])->toBe('Profil backend de 3,5 ans.');
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request['messages'], JSON_UNESCAPED_UNICODE),
        'ne respecte pas le schéma',
    ));
});

it('throws after two invalid outputs', function (): void {
    $invalid = validLlmPayload(['skills' => 'not-an-array']);
    Http::fake([
        '*' => Http::sequence()
            ->push(llmResponse($invalid))
            ->push(llmResponse($invalid)),
    ]);

    expect(fn () => makeLlmAnalyzer()->analyze('CV', ['title' => 'Dev']))
        ->toThrow(InvalidAnalysisOutputException::class);

    Http::assertSentCount(2);
});

it('maps 429 to quota_exceeded', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Rate limit reached']], 429)]);

    try {
        makeLlmAnalyzer()->analyze('CV', ['title' => 'Dev']);
        expect(false)->toBeTrue();
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe('quota_exceeded');
    }
});

it('maps connection failures to provider_timeout', function (): void {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28')]);

    try {
        makeLlmAnalyzer()->analyze('CV', ['title' => 'Dev']);
        expect(false)->toBeTrue();
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe('provider_timeout');
    }
});

it('sends json_schema response_format in structured mode', function (): void {
    $analyzer = makeLlmAnalyzer();
    config()->set('llm.structured_output', true);
    Http::fake(['*' => llmResponse(validLlmPayload())]);

    $analyzer->analyze('CV', ['title' => 'Dev']);

    Http::assertSent(fn (Request $request): bool => $request['response_format']['type'] === 'json_schema');
});

it('falls back to json_object when upstream schema validation rejects the generation', function (): void {
    $analyzer = makeLlmAnalyzer();
    config()->set('llm.structured_output', true);
    Http::fake([
        '*' => Http::sequence()
            ->push(['error' => ['message' => "Failed to validate JSON. Please adjust your prompt. See 'failed_generation' for more details."]], 400)
            ->push(llmResponse(validLlmPayload())),
    ]);

    $result = $analyzer->analyze('CV', ['title' => 'Dev']);

    expect($result->payload['summary'])->toBe('Profil backend de 3,5 ans.');

    $requests = Http::recorded(fn (Request $request): bool => true)->map(fn ($pair) => $pair[0]);
    expect($requests[0]['response_format']['type'])->toBe('json_schema')
        ->and($requests[1]['response_format']['type'])->toBe('json_object')
        // The schema must be in the prompt so json_object mode keeps the shape.
        ->and(json_encode($requests[1]['messages'], JSON_UNESCAPED_UNICODE))
        ->toContain('years_experience_total');
});

it('surfaces non-validation provider errors without falling back', function (): void {
    $analyzer = makeLlmAnalyzer();
    config()->set('llm.structured_output', true);
    Http::fake(['*' => Http::response(['error' => ['message' => 'Server error']], 500)]);

    expect(fn () => $analyzer->analyze('CV', ['title' => 'Dev']))
        ->toThrow(ProviderException::class);

    Http::assertSentCount(1);
});

it('provides a deterministic fake analyzer whose sample validates', function (): void {
    $sample = FakeCvAnalyzer::sample([
        'title' => 'Dev',
        'required_skills' => ['php', 'laravel', 'redis'],
        'min_experience_years' => 3,
    ]);

    expect((new AnalysisSchemaValidator)->validate($sample))->toBe([]);

    $result = (new FakeCvAnalyzer)->analyze('CV', ['title' => 'Dev']);
    expect($result->provider)->toBe('fake');

    expect(fn () => (new FakeCvAnalyzer(failWith: 'boom'))->analyze('CV', []))
        ->toThrow(InvalidAnalysisOutputException::class);
});
