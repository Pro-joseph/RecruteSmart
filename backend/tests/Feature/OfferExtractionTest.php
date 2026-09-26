<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('llm.api_key', 'test-key');
    config()->set('llm.model', 'llama-test');
    config()->set('llm.structured_output', false);
    $this->user = User::factory()->create();
});

function extractJdText(): string
{
    return <<<'JD'
        Full-Stack Web Developer

        Company: Interconnection Consulting – B2B Market Research, Big Data & Digital Solutions
        Location: Agadir / Home Office

        We are looking for a skilled Full-Stack Web Developer to develop and maintain our
        websites, web applications and digital business tools. You will build responsive
        frontend interfaces, backend services, APIs and database-driven applications.

        Required: HTML, CSS, JavaScript/TypeScript, React or Vue, SQL, REST APIs, Git.
        Nice to have: Azure, Docker, CI/CD, PHP.

        Job Type: Full-time
        Pay: 6,000.00DH - 10,000.00DH per month
        Work Location: In person
        JD;
}

/** @return array<string, mixed> */
function extractPayload(array $overrides = []): array
{
    return [
        'title' => 'Full-Stack Web Developer',
        'type' => 'full_time',
        'type_label' => null,
        'description' => 'Develop and maintain websites, web applications and digital business tools.',
        'missions' => "Build responsive frontend interfaces.\nDevelop backend APIs and database-driven applications.",
        'profile_wanted' => 'Strong knowledge of HTML, CSS, JavaScript/TypeScript and REST APIs.',
        'city' => 'Agadir',
        'country' => null,
        'work_mode' => 'hybrid',
        'salary_min' => 6000,
        'salary_max' => 10000,
        'salary_currency' => 'MAD',
        'positions_count' => null,
        'required_skills' => ['HTML', 'CSS', 'JavaScript', 'TypeScript', 'React', 'SQL', 'REST APIs', 'Git'],
        'preferred_skills' => ['Azure', 'Docker', 'CI/CD', 'PHP'],
        'min_experience_years' => null,
        'education_level' => null,
        'languages' => [['name' => 'English', 'level' => null]],
        ...$overrides,
    ];
}

function extractLlmResponse(array $payload): array
{
    return [
        'model' => 'llama-test',
        'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ];
}

it('extracts offer fields from a pasted job description', function (): void {
    Http::fake(['*' => extractLlmResponse(extractPayload())]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertOk()
        ->assertJsonPath('data.title', 'Full-Stack Web Developer')
        ->assertJsonPath('data.type', 'full_time')
        ->assertJsonPath('data.city', 'Agadir')
        ->assertJsonPath('data.work_mode', 'hybrid')
        ->assertJsonPath('data.salary_min', 6000)
        ->assertJsonPath('data.salary_max', 10000)
        ->assertJsonPath('data.salary_currency', 'MAD')
        ->assertJsonPath('data.required_skills.0', 'HTML')
        ->assertJsonPath('data.languages.0.name', 'English');
});

it('sanitizes extracted values before returning them', function (): void {
    Http::fake(['*' => extractLlmResponse(extractPayload([
        'type' => 'fulltime',
        'work_mode' => 'wfh',
        'salary_min' => 12000,
        'salary_max' => 6000,
        'salary_currency' => 'DH',
        'positions_count' => 0,
        'required_skills' => ['  ', str_repeat('x', 100), 'PHP', 'PHP'],
        'title' => '   ',
        'type_label' => 'Full-time',
    ]))]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertOk()
        ->assertJsonPath('data.type', 'full_time')
        ->assertJsonPath('data.type_label', null)
        ->assertJsonPath('data.work_mode', null)
        ->assertJsonPath('data.salary_min', 6000)
        ->assertJsonPath('data.salary_max', 12000)
        ->assertJsonPath('data.salary_currency', 'MAD')
        ->assertJsonPath('data.positions_count', 1)
        ->assertJsonPath('data.title', null)
        ->assertJsonPath('data.required_skills', [str_repeat('x', 64), 'PHP']);
});

it('maps a localized type label when type is missing', function (): void {
    Http::fake(['*' => extractLlmResponse(extractPayload([
        'type' => null,
        'type_label' => 'Part-time',
    ]))]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertOk()
        ->assertJsonPath('data.type', 'part_time')
        ->assertJsonPath('data.type_label', null);
});

it('retries once when the model output misses required keys', function (): void {
    Http::fake([
        '*' => Http::sequence()
            ->push(extractLlmResponse(['title' => 'Only title']))
            ->push(extractLlmResponse(extractPayload())),
    ]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertOk()
        ->assertJsonPath('data.title', 'Full-Stack Web Developer');
});

it('falls back to json_object when strict schema generation fails upstream', function (): void {
    config()->set('llm.structured_output', true);
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'error' => [
                    'message' => "Failed to generate JSON. Please adjust your prompt. See 'failed_generation' for more details.",
                ],
            ], 400)
            ->push(extractLlmResponse(extractPayload())),
    ]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertOk()
        ->assertJsonPath('data.title', 'Full-Stack Web Developer');
});

it('returns 502 when extraction output stays invalid', function (): void {
    Http::fake(['*' => extractLlmResponse(['title' => 'Only title'])]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Extraction invalide après 3 tentatives.');
});

it('maps provider failures to an error message', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Quota']], 429)]);
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])
        ->assertStatus(429)
        ->assertJsonPath('message', 'Quota fournisseur IA atteint.');
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/offers/extract', ['text' => extractJdText()])->assertUnauthorized();
});

it('validates the pasted text', function (): void {
    $this->actingAs($this->user);

    $this->postJson('/api/v1/offers/extract', ['text' => 'too short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);

    $this->postJson('/api/v1/offers/extract', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);

    $this->postJson('/api/v1/offers/extract', ['text' => str_repeat('a', 30001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});
