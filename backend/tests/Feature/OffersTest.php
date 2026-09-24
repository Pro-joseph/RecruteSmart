<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function offerPayload(array $overrides = []): array
{
    return [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
        ...$overrides,
    ];
}

it('creates an offer with seeded locked fields', function (): void {
    $response = $this->postJson('/api/v1/offers', offerPayload());

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(3, 'data.form_fields');
});

it('validates offer input', function (): void {
    $this->postJson('/api/v1/offers', ['title' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'description']);

    $this->postJson('/api/v1/offers', offerPayload(['type' => 'other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type_label']);
});

it('isolates offers between recruiters', function (): void {
    $other = User::factory()->create();
    $offer = Offer::factory()->for($other)->create();

    $this->getJson("/api/v1/offers/{$offer->id}")->assertForbidden();
    $this->putJson("/api/v1/offers/{$offer->id}", ['title' => 'Hijack'])->assertForbidden();
    $this->deleteJson("/api/v1/offers/{$offer->id}")->assertForbidden();
    $this->postJson("/api/v1/offers/{$offer->id}/publish")->assertForbidden();

    $mine = Offer::factory()->for($this->user)->create();
    $this->getJson("/api/v1/offers/{$mine->id}")->assertOk();
});

it('publishes, regenerates, duplicates and closes', function (): void {
    $id = $this->postJson('/api/v1/offers', offerPayload())->json('data.id');

    $published = $this->postJson("/api/v1/offers/{$id}/publish")->assertOk();
    $token = $published->json('data.public_token');
    expect($token)->toHaveLength(32);
    expect($published->json('data.public_url'))->toEndWith("/apply/{$token}");

    $regen = $this->postJson("/api/v1/offers/{$id}/regenerate-link")->assertOk();
    expect($regen->json('data.public_token'))->not->toBe($token);

    $copy = $this->postJson("/api/v1/offers/{$id}/duplicate")->assertCreated();
    expect($copy->json('data.status'))->toBe('draft')
        ->and($copy->json('data.public_token'))->toBeNull();

    $this->postJson("/api/v1/offers/{$id}/close")->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

it('bumps criteria_version when criteria change', function (): void {
    $id = $this->postJson('/api/v1/offers', offerPayload())->json('data.id');

    $this->putJson("/api/v1/offers/{$id}", ['required_skills' => ['php', 'redis']])
        ->assertOk()
        ->assertJsonPath('data.criteria_version', 2);
});

it('deletes offers without applications', function (): void {
    $id = $this->postJson('/api/v1/offers', offerPayload())->json('data.id');

    $this->deleteJson("/api/v1/offers/{$id}")->assertNoContent();
    $this->getJson("/api/v1/offers/{$id}")->assertNotFound();
});
