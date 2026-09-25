<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Skill;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $service = app(OfferService::class);
    $this->offer = $service->publish($service->create($this->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));

    $laravel = Skill::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']);
    $php = Skill::firstOrCreate(['slug' => 'php'], ['name' => 'PHP']);

    $this->alice = makeApplication('Alice Wonder', 'alice@x.com', now()->subDays(3), [
        'match_score' => 90,
        'ats_score' => 80,
        'ats_verdict' => 'compliant',
        'years_experience' => '5.0',
        'city' => 'Casablanca',
        'city_normalized' => 'casablanca',
        'country' => 'Maroc',
    ], ['laravel', 'php']);
    $this->bob = makeApplication('Bob Builder', 'bob@y.com', now()->subDays(2), [
        'match_score' => 60,
        'ats_score' => 45,
        'ats_verdict' => 'non_compliant',
        'years_experience' => '1.0',
        'city' => 'Rabat',
        'city_normalized' => 'rabat',
        'country' => 'Maroc',
    ], ['php'], ['status' => 'shortlisted']);
    $this->charlie = makeApplication('Charlie Chaplin', 'charlie@x.com', now()->subDay(), [
        'match_score' => 75,
        'ats_score' => 60,
        'ats_verdict' => 'improvable',
        'years_experience' => '3.0',
        'city' => 'Paris',
        'city_normalized' => 'paris',
        'country' => 'France',
    ], ['laravel']);
    $this->dana = makeApplication('Dana Scully', 'dana@x.com', now(), [], []);
});

/**
 * @param  list<string>  $slugs
 * @param  array<string, mixed>  $analysis
 * @param  array<string, mixed>  $attributes
 */
function makeApplication(
    string $name,
    string $email,
    Carbon $createdAt,
    array $analysis,
    array $slugs,
    array $attributes = [],
): Application {
    $application = Application::factory()->create([
        'offer_id' => test()->offer->id,
        'full_name' => $name,
        'email' => $email,
        'created_at' => $createdAt,
        ...$attributes,
    ]);

    if ($analysis !== []) {
        ApplicationAnalysis::factory()->completed()->create([
            'application_id' => $application->id,
            ...$analysis,
        ]);
    }

    if ($slugs !== []) {
        $ids = [];
        foreach ($slugs as $slug) {
            $ids[] = Skill::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id;
        }
        $application->skills()->attach($ids);
    }

    return $application;
}

function filterIds(TestResponse $response): array
{
    return array_column($response->json('data'), 'id');
}

it('sorts by match score descending by default and reports the count (EF-504/807)', function (): void {
    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$this->offer->id}/applications")
        ->assertOk();

    expect(filterIds($response))->toBe([
        $this->alice->id,
        $this->charlie->id,
        $this->bob->id,
        $this->dana->id,
    ]);
    expect($response->json('meta.total'))->toBe(4);
});

it('filters by score range and ATS verdict or range (EF-801/802)', function (): void {
    $url = "/api/v1/offers/{$this->offer->id}/applications";

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?min_score=70")))
        ->toBe([$this->alice->id, $this->charlie->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?min_score=70&max_score=75")))
        ->toBe([$this->charlie->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?ats=compliant")))
        ->toBe([$this->alice->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?ats_min=50&ats_max=70")))
        ->toBe([$this->charlie->id]);

    // Verdict OR range inside the ATS family.
    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?ats=compliant&ats_min=90")))
        ->toBe([$this->alice->id]);
});

it('filters by skills (any/all) and experience range (EF-803/804)', function (): void {
    $url = "/api/v1/offers/{$this->offer->id}/applications";

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?skills[]=laravel")))
        ->toBe([$this->alice->id, $this->charlie->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?skills[]=laravel&skills[]=php")))
        ->toBe([$this->alice->id, $this->charlie->id, $this->bob->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?skills[]=laravel&skills[]=php&skills_mode=all")))
        ->toBe([$this->alice->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?exp_min=3")))
        ->toBe([$this->alice->id, $this->charlie->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?exp_max=2")))
        ->toBe([$this->bob->id]);
});

it('filters by place, status and application date (EF-805/806)', function (): void {
    $url = "/api/v1/offers/{$this->offer->id}/applications";

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?city=Casablanca")))
        ->toBe([$this->alice->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?country=MAROC")))
        ->toBe([$this->alice->id, $this->bob->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?status[]=new")))
        ->toBe([$this->alice->id, $this->charlie->id, $this->dana->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?status[]=new&status[]=shortlisted")))
        ->toBe([$this->alice->id, $this->charlie->id, $this->bob->id, $this->dana->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?applied_from=".now()->subDays(2)->toDateString())))
        ->toBe([$this->charlie->id, $this->bob->id, $this->dana->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?applied_to=".now()->subDays(2)->toDateString())))
        ->toBe([$this->alice->id, $this->bob->id]);
});

it('searches by name, email and skills (EF-507)', function (): void {
    $url = "/api/v1/offers/{$this->offer->id}/applications";

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?q=alice")))
        ->toBe([$this->alice->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?q=bob@y.com")))
        ->toBe([$this->bob->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?q=laravel")))
        ->toBe([$this->alice->id, $this->charlie->id]);
});

it('supports the sort whitelist and rejects invalid filter values', function (): void {
    $url = "/api/v1/offers/{$this->offer->id}/applications";

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?sort=full_name")))
        ->toBe([$this->alice->id, $this->bob->id, $this->charlie->id, $this->dana->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?sort=ats_score")))
        ->toBe([$this->dana->id, $this->bob->id, $this->charlie->id, $this->alice->id]);

    expect(filterIds($this->actingAs($this->owner)->getJson("{$url}?sort=-created_at")))
        ->toBe([$this->dana->id, $this->charlie->id, $this->bob->id, $this->alice->id]);

    $this->actingAs($this->owner)->getJson("{$url}?min_score=abc")->assertUnprocessable();
    $this->actingAs($this->owner)->getJson("{$url}?ats=bogus")->assertUnprocessable();
    $this->actingAs($this->owner)->getJson("{$url}?status[]=zombie")->assertUnprocessable();
});
