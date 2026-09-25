<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\Forward;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
    $this->owner = User::factory()->create();
    $offer = Offer::factory()->create(['user_id' => $this->owner->id]);
    $this->application = Application::factory()->create(['offer_id' => $offer->id]);
    $this->application->setRelation('offer', $offer);

    Storage::disk('private')->put($this->application->cv_path, 'cv-bytes');
    $this->application->update([
        'files' => [[
            'key' => 'portfolio',
            'path' => "applications/{$this->application->offer_id}/extra.pdf",
            'original_name' => 'portfolio.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
        ]],
    ]);
    Storage::disk('private')->put("applications/{$this->application->offer_id}/extra.pdf", 'extra');

    Interview::factory()->create(['application_id' => $this->application->id]);

    $forward = Forward::factory()->create(['user_id' => $this->owner->id]);
    $forward->applications()->attach($this->application->id, [
        'candidate_name_snapshot' => $this->application->full_name,
    ]);
    $this->forward = $forward;
});

it('deletes rows, files and replaces the forward snapshot (EF-1201, RG-13)', function (): void {
    $cvPath = $this->application->cv_path;
    $extraPath = "applications/{$this->application->offer_id}/extra.pdf";
    $id = $this->application->id;

    $this->actingAs($this->owner)
        ->deleteJson("/api/v1/applications/{$id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('applications', ['id' => $id]);
    $this->assertDatabaseMissing('application_analyses', ['application_id' => $id]);
    $this->assertDatabaseMissing('application_events', ['application_id' => $id]);
    $this->assertDatabaseMissing('interviews', ['application_id' => $id]);

    Storage::disk('private')->assertMissing($cvPath);
    Storage::disk('private')->assertMissing($extraPath);

    $snapshot = DB::table('forward_application')->where('forward_id', $this->forward->id)->sole();
    expect($snapshot->candidate_name_snapshot)->toBe('Candidat supprimé')
        ->and($snapshot->application_id)->toBeNull();

    $log = DB::table('audit_logs')->where('action', AuditAction::ApplicationDeleted->value)->sole();
    expect($log->subject_id)->toBe($id)
        ->and($log->user_id)->toBe($this->owner->id);
});

it('refuses deletion for another recruiter (EF-1201)', function (): void {
    $this->actingAs(User::factory()->create())
        ->deleteJson("/api/v1/applications/{$this->application->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('applications', ['id' => $this->application->id]);
});
