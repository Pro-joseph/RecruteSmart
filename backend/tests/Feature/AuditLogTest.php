<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    $this->owner = User::factory()->create();
    $service = app(OfferService::class);
    $this->offer = $service->publish($service->create($this->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]));
    $this->application = Application::factory()->create(['offer_id' => $this->offer->id]);
    Storage::disk('private')->put($this->application->cv_path, 'cv-bytes');
});

it('records a CV download on the authenticated route (EF-1204)', function (): void {
    $this->actingAs($this->owner)
        ->get("/api/v1/applications/{$this->application->id}/files/cv")
        ->assertOk();

    $log = DB::table('audit_logs')->sole();

    expect($log->action)->toBe(AuditAction::CvDownloaded->value)
        ->and($log->user_id)->toBe($this->owner->id)
        ->and($log->subject_type)->toBe('applications')
        ->and($log->subject_id)->toBe($this->application->id)
        ->and((string) $log->ip)->not->toBe('');
});

it('records a signed-link CV download without a user (EF-1204)', function (): void {
    $url = URL::temporarySignedRoute('forwarded-cv', now()->addDay(), [
        'application' => $this->application->id,
    ]);

    $this->get($url)->assertOk();

    $log = DB::table('audit_logs')->where('action', AuditAction::CvDownloaded->value)->sole();

    expect($log->user_id)->toBeNull()
        ->and(json_decode((string) $log->metadata, true))->toBe(['via' => 'signed_link']);
});

it('records a regenerated public link (EF-1204)', function (): void {
    $token = $this->offer->public_token;

    $this->actingAs($this->owner)
        ->postJson("/api/v1/offers/{$this->offer->id}/regenerate-link")
        ->assertOk();

    $log = DB::table('audit_logs')->where('action', AuditAction::LinkRegenerated->value)->sole();

    expect($log->user_id)->toBe($this->owner->id)
        ->and($log->subject_type)->toBe('offers')
        ->and($log->subject_id)->toBe($this->offer->id)
        ->and($this->offer->fresh()->public_token)->not->toBe($token);
});

it('keeps foreign recruiters out of the audit trail targets', function (): void {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get("/api/v1/applications/{$this->application->id}/files/cv")
        ->assertForbidden();

    expect(DB::table('audit_logs')->count())->toBe(0);
});
