<?php

declare(strict_types=1);

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(OfferService::class);
    $this->user = User::factory()->create();
});

it('seeds locked fields on creation', function (): void {
    $offer = $this->service->create($this->user, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]);

    expect($offer->formFields->pluck('key')->all())
        ->toBe(['full_name', 'email', 'cv'])
        ->and($offer->status)->toBe(OfferStatus::Draft);
});

it('bumps criteria_version only when criteria change', function (): void {
    /** @var Offer $offer */
    $offer = Offer::factory()->for($this->user)->create();

    $this->service->update($offer, ['title' => 'New title']);
    expect($offer->fresh()?->criteria_version)->toBe(1);

    $this->service->update($offer, ['required_skills' => ['php', 'redis']]);
    expect($offer->fresh()?->criteria_version)->toBe(2);
});

it('publishes idempotently with a unique token', function (): void {
    /** @var Offer $offer */
    $offer = Offer::factory()->for($this->user)->create();

    $published = $this->service->publish($offer);

    expect($published->status)->toBe(OfferStatus::Published)
        ->and($published->public_token)->toHaveLength(32)
        ->and($published->published_at)->not->toBeNull();

    $again = $this->service->publish($published->fresh() ?? $published);
    expect($again->public_token)->toBe($published->public_token);
});

it('duplicates as draft with copied fields and fresh token', function (): void {
    $offer = $this->service->create($this->user, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]);
    $this->service->publish($offer);

    $copy = $this->service->duplicate($this->user, $offer->fresh() ?? $offer);

    expect($copy->id)->not->toBe($offer->id)
        ->and($copy->status)->toBe(OfferStatus::Draft)
        ->and($copy->public_token)->toBeNull()
        ->and($copy->title)->toEndWith('(copie)')
        ->and($copy->formFields->pluck('key')->all())->toBe(['full_name', 'email', 'cv']);
});

it('regenerates the public link', function (): void {
    /** @var Offer $offer */
    $offer = Offer::factory()->for($this->user)->create();
    $before = $this->service->publish($offer);
    $oldToken = $before->public_token;

    $after = $this->service->regenerateLink($before);

    expect($after->public_token)->not->toBe($oldToken)
        ->and($after->public_token)->toHaveLength(32);
});
