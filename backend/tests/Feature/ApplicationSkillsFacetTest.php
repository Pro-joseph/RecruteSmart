<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Skill;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $service = app(OfferService::class);
    $this->offer = $service->publish($service->create($this->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));
});

it('returns distinct skills of the offer candidates with counts (EF-803)', function (): void {
    $laravel = Skill::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']);
    $php = Skill::firstOrCreate(['slug' => 'php'], ['name' => 'PHP']);
    $vue = Skill::firstOrCreate(['slug' => 'vue'], ['name' => 'Vue']);

    $alice = Application::factory()->create(['offer_id' => $this->offer->id]);
    $alice->skills()->attach([$laravel->id, $php->id]);
    ApplicationAnalysis::factory()->completed()->create(['application_id' => $alice->id]);

    $bob = Application::factory()->create(['offer_id' => $this->offer->id]);
    $bob->skills()->attach([$laravel->id]);

    $foreign = Application::factory()->create(['offer_id' => $this->offer->id]);
    $foreign->skills()->attach([$vue->id]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$this->offer->id}/skills")
        ->assertOk();

    $response->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.slug', 'laravel');
    $response->assertJsonPath('data.0.applications_count', 2);
    $response->assertJsonPath('data.1.slug', 'php');
    $response->assertJsonPath('data.1.applications_count', 1);
    $response->assertJsonPath('data.2.slug', 'vue');
    $response->assertJsonPath('data.2.applications_count', 1);
});

it('scopes skills to the offer and rejects non-owners', function (): void {
    $laravel = Skill::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']);
    $application = Application::factory()->create(['offer_id' => $this->offer->id]);
    $application->skills()->attach([$laravel->id]);

    $otherOwner = User::factory()->create();
    $service = app(OfferService::class);
    $otherOffer = $service->publish($service->create($otherOwner, [
        'title' => 'Autre offre',
        'type' => 'full_time',
        'description' => 'Autre',
    ]));
    $otherApplication = Application::factory()->create(['offer_id' => $otherOffer->id]);
    $otherApplication->skills()->attach([$laravel->id]);

    $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$otherOffer->id}/skills")
        ->assertForbidden();

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/offers/{$this->offer->id}/skills")
        ->assertOk()
        ->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.slug', 'laravel');
    $response->assertJsonPath('data.0.applications_count', 1);
});
