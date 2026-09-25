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
    $offer = Offer::factory()->create(['user_id' => $this->owner->id]);
    $this->application = Application::factory()->create(['offer_id' => $offer->id]);

    Storage::disk('private')->put($this->application->cv_path, 'cv-bytes');
    $this->application->update([
        'files' => [[
            'key' => 'portfolio',
            'path' => "applications/{$offer->id}/extra.pdf",
            'original_name' => 'portfolio.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
        ]],
    ]);
    Storage::disk('private')->put("applications/{$offer->id}/extra.pdf", 'extra-bytes');
});

it('exports a candidate file as a ZIP with data, CV and attachments (EF-1203, RG-14)', function (): void {
    $response = $this->actingAs($this->owner)
        ->get("/api/v1/applications/{$this->application->id}/export");

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $path = $response->baseResponse->getFile()->getPathname();

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    expect($names)->toContain('donnees.json', 'cv/'.$this->application->cv_original_name, 'fichiers/portfolio.pdf');

    $payload = json_decode($zip->getFromName('donnees.json'), true);
    expect($payload['full_name'])->toBe($this->application->full_name)
        ->and($payload['offer']['id'])->toBe($this->application->offer_id)
        ->and($zip->getFromName('cv/'.$this->application->cv_original_name))->toBe('cv-bytes');

    $zip->close();

    $log = DB::table('audit_logs')->where('action', AuditAction::ApplicationExported->value)->sole();
    expect($log->subject_id)->toBe($this->application->id)
        ->and($log->user_id)->toBe($this->owner->id);
});

it('refuses export to another recruiter (EF-1203)', function (): void {
    $this->actingAs(User::factory()->create())
        ->get("/api/v1/applications/{$this->application->id}/export")
        ->assertForbidden();
});
