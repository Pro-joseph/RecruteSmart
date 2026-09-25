<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    $this->owner = User::factory()->create();
    $this->offer = Offer::factory()->create(['user_id' => $this->owner->id]);
});

it('purges applications past the retention period, files included (EF-1202)', function (): void {
    $expired = Application::factory()->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subMonths(13),
    ]);
    $recent = Application::factory()->create(['offer_id' => $this->offer->id]);

    Storage::disk('private')->put($expired->cv_path, 'old-cv');
    Storage::disk('private')->put($recent->cv_path, 'new-cv');

    $this->artisan('applications:purge-expired')
        ->expectsOutputToContain('1 application(s)')
        ->assertSuccessful();

    $this->assertDatabaseMissing('applications', ['id' => $expired->id]);
    $this->assertDatabaseHas('applications', ['id' => $recent->id]);

    Storage::disk('private')->assertMissing($expired->cv_path);
    Storage::disk('private')->assertExists($recent->cv_path);

    expect(DB::table('audit_logs')->where('action', AuditAction::ApplicationPurged->value)->count())->toBe(1);
});

it('reports nothing when every application is within retention (EF-1202)', function (): void {
    Application::factory()->create(['offer_id' => $this->offer->id]);

    $this->artisan('applications:purge-expired')
        ->expectsOutput('No expired application.')
        ->assertSuccessful();

    expect(Application::count())->toBe(1);
});

it('supports a dry run that deletes nothing (EF-1202)', function (): void {
    $expired = Application::factory()->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subMonths(13),
    ]);

    $this->artisan('applications:purge-expired --dry-run')->assertSuccessful();

    $this->assertDatabaseHas('applications', ['id' => $expired->id]);
});
