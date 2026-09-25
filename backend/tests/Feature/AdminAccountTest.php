<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists users with their offers and applications (EF-1205)', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $offer = Offer::factory()->create(['user_id' => $owner->id]);
    Application::factory()->count(2)->create(['offer_id' => $offer->id]);

    $response = $this->actingAs($admin)->get('/api/v1/admin/users');

    $response->assertOk();

    $ownerRow = collect($response->json('data'))->firstWhere('email', $owner->email);

    expect($ownerRow['offers_count'])->toBe(1)
        ->and($ownerRow['applications_count'])->toBe(2)
        ->and($ownerRow['is_admin'])->toBeFalse();
});

it('reports AI usage totals and per-model token consumption (EF-1205)', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $offer = Offer::factory()->create();

    foreach ([[900, 100], [300, 50]] as [$in, $out]) {
        $application = Application::factory()->create(['offer_id' => $offer->id]);
        ApplicationAnalysis::factory()->create([
            'application_id' => $application->id,
            'status' => 'completed',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4o-mini',
            'tokens_in' => $in,
            'tokens_out' => $out,
        ]);
    }

    $response = $this->actingAs($admin)->get('/api/v1/admin/usage');

    $response->assertOk();

    expect($response->json('data.totals.analyses_count'))->toBe(2)
        ->and($response->json('data.totals.completed_count'))->toBe(2)
        ->and($response->json('data.totals.tokens_in'))->toBe(1200)
        ->and($response->json('data.totals.tokens_out'))->toBe(150)
        ->and($response->json('data.per_model.0.llm_model'))->toBe('gpt-4o-mini');
});

it('refuses admin routes to a regular recruiter (EF-1205)', function (): void {
    $recruiter = User::factory()->create();

    $this->actingAs($recruiter)->get('/api/v1/admin/users')->assertForbidden();
    $this->actingAs($recruiter)->get('/api/v1/admin/usage')->assertForbidden();
});

it('refuses admin routes to guests (EF-1205)', function (): void {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();
});

it('exposes is_admin on the session profile (EF-1205)', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.is_admin', true);
});

it('promotes an account with user:make-admin (EF-1205)', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->artisan('user:make-admin '.$user->email)
        ->expectsOutput("{$user->email} is now an administrator.")
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('fails user:make-admin for an unknown account (EF-1205)', function (): void {
    $this->artisan('user:make-admin nobody@example.test')
        ->expectsOutputToContain('User not found.')
        ->assertFailed();
});
