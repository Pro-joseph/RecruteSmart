<?php

declare(strict_types=1);

use App\Mail\ApplicationReceivedMail;
use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('private');
    Mail::fake();
    $this->owner = User::factory()->create();
    $this->service = app(OfferService::class);
});

function publishedOffer(): Offer
{
    $offer = test()->service->create(test()->owner, [
        'title' => 'Dev Laravel',
        'type' => 'full_time',
        'description' => 'Backend API',
    ]);

    return test()->service->publish($offer);
}

function cvFile(): UploadedFile
{
    return UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
}

function submitPayload(array $overrides = []): array
{
    return [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'consent' => '1',
        'files' => ['cv' => cvFile()],
        ...$overrides,
    ];
}

it('shows the public offer with form fields and accepting flag', function (): void {
    $offer = publishedOffer();

    $this->getJson("/api/v1/public/offers/{$offer->public_token}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Dev Laravel')
        ->assertJsonPath('data.accepting_applications', true)
        ->assertJsonCount(3, 'data.form_fields');
});

it('returns 404 for unknown tokens', function (): void {
    $this->getJson('/api/v1/public/offers/unknown-token-12345678')->assertNotFound();
    $this->postJson('/api/v1/public/offers/unknown-token-12345678/applications', submitPayload())
        ->assertNotFound();
});

it('stores a valid application with files and sends confirmation', function (): void {
    $offer = publishedOffer();

    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", submitPayload())
        ->assertCreated();

    $this->assertDatabaseHas('applications', [
        'offer_id' => $offer->id,
        'email' => 'jane@example.com',
        'consent_version' => 'v1',
    ]);

    $application = $offer->applications()->firstOrFail();
    Storage::disk('private')->assertExists($application->cv_path);
    expect($application->consent_at)->not->toBeNull();

    Mail::assertSent(ApplicationReceivedMail::class);
});

it('rejects invalid files, missing consent and bad fields', function (): void {
    $offer = publishedOffer();
    $url = "/api/v1/public/offers/{$offer->public_token}/applications";

    $this->postJson($url, submitPayload(['files' => ['cv' => UploadedFile::fake()->create('cv.exe', 100, 'application/x-msdownload')]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.cv']);

    $this->postJson($url, submitPayload(['files' => ['cv' => UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf')]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.cv']);

    $this->postJson($url, submitPayload(['consent' => '0']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['consent']);

    $this->postJson($url, submitPayload(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects duplicate emails per offer with a dedicated message', function (): void {
    $offer = publishedOffer();
    $url = "/api/v1/public/offers/{$offer->public_token}/applications";

    $this->postJson($url, submitPayload())->assertCreated();
    $this->postJson($url, submitPayload(['full_name' => 'Jane Other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email'])
        ->assertJsonFragment(['Cette adresse email a déjà postulé à cette offre.']);
});

it('refuses submissions to closed or draft offers', function (): void {
    $offer = publishedOffer();
    $this->service->close($offer->fresh() ?? $offer);

    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", submitPayload())
        ->assertUnprocessable();

    $draft = $this->service->create($this->owner, [
        'title' => 'Draft', 'type' => 'full_time', 'description' => 'x',
    ]);
    $draft->forceFill(['public_token' => 'draft-token-12345678901234'])->save();

    $this->postJson('/api/v1/public/offers/draft-token-12345678901234/applications', submitPayload())
        ->assertUnprocessable();
});

it('rejects honeypot-filled submissions', function (): void {
    $offer = publishedOffer();

    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", submitPayload(['website' => 'bot']))
        ->assertUnprocessable();
});

it('lists applications only for the owner and downloads the CV', function (): void {
    $offer = publishedOffer();
    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", submitPayload())
        ->assertCreated();

    $other = User::factory()->create();
    $this->actingAs($other);
    $this->getJson("/api/v1/offers/{$offer->id}/applications")->assertForbidden();

    $this->actingAs($this->owner);
    $list = $this->getJson("/api/v1/offers/{$offer->id}/applications")->assertOk();
    expect($list->json('data'))->toHaveCount(1);

    $application = $offer->applications()->firstOrFail();
    $this->get("/api/v1/applications/{$application->id}/files/cv")
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($other);
    $this->get("/api/v1/applications/{$application->id}/files/cv")->assertForbidden();
});

it('enforces RG-12: used fields can only be hidden, not deleted', function (): void {
    $offer = publishedOffer();
    $locked = [
        ['key' => 'full_name', 'label' => 'Nom', 'type' => 'text', 'is_required' => true],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
        ['key' => 'cv', 'label' => 'CV', 'type' => 'file', 'is_required' => true],
        ['key' => 'phone', 'label' => 'Tél', 'type' => 'phone'],
    ];

    $this->actingAs($this->owner);
    $this->putJson("/api/v1/offers/{$offer->id}/form-fields", ['fields' => $locked])->assertOk();

    $this->postJson("/api/v1/public/offers/{$offer->public_token}/applications", submitPayload(['phone' => '+212600000000']))
        ->assertCreated();

    // Deleting the used phone field is refused…
    $this->putJson("/api/v1/offers/{$offer->id}/form-fields", ['fields' => array_slice($locked, 0, 3)])
        ->assertUnprocessable();

    // …but hiding it is allowed.
    $hidden = [...array_slice($locked, 0, 3), ['key' => 'phone', 'label' => 'Tél', 'type' => 'phone', 'is_hidden' => true]];
    $this->putJson("/api/v1/offers/{$offer->id}/form-fields", ['fields' => $hidden])->assertOk();
});
