<?php

declare(strict_types=1);

use App\Mail\ApplicationReceivedMail;
use App\Mail\CandidateRejectedMail;
use App\Mail\InterviewInvitationMail;
use App\Models\Application;
use App\Models\EmailTemplate;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    $this->recruiter = User::factory()->create();
    $this->offer = Offer::factory()->create([
        'user_id' => $this->recruiter->id,
        'title' => 'Dev Laravel',
        'status' => 'published',
    ]);
    $this->application = Application::factory()->create([
        'offer_id' => $this->offer->id,
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
});

it('sends the built-in copy when no template is customised', function (): void {
    expect(EmailTemplate::defaults()['confirmation']['subject'])->toContain('{offer_title}');

    $mail = new ApplicationReceivedMail($this->application);

    expect($mail->envelope()->subject)->toBe('Candidature bien reçue — Dev Laravel');
    expect($mail->render())->toContain('Jane Doe');
});

it('reads a customised template with placeholders (EF-906)', function (): void {
    $this->recruiter->emailTemplates()->create([
        'key' => EmailTemplate::CONFIRMATION,
        'subject' => 'Bienvenue chez nous — {offer_title}',
        'body' => "Salut {candidate_name},\nOn a bien reçu ta candidature !",
    ]);

    $mail = new ApplicationReceivedMail($this->application);

    expect($mail->envelope()->subject)->toBe('Bienvenue chez nous — Dev Laravel');
    expect($mail->render())->toContain('Salut Jane Doe');
});

it('exposes the templates on settings endpoints with a reset', function (): void {
    $token = $this->recruiter->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/settings/email-templates')
        ->assertOk()
        ->assertJsonPath('data.confirmation.customised', false)
        ->assertJsonPath('data.invitation.customised', false);

    $this->withToken($token)
        ->putJson('/api/v1/settings/email-templates/invitation', [
            'subject' => 'Entretien — {offer_title}',
            'body' => 'Bonjour {candidate_name}, rendez-vous le {starts_at}.',
        ])->assertOk();

    $this->withToken($token)
        ->getJson('/api/v1/settings/email-templates')
        ->assertJsonPath('data.invitation.customised', true)
        ->assertJsonPath('data.invitation.subject', 'Entretien — {offer_title}');

    $this->withToken($token)
        ->deleteJson('/api/v1/settings/email-templates/invitation')
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/v1/settings/email-templates')
        ->assertJsonPath('data.invitation.customised', false);
});

it('uses the invitation and refusal copies in their mailables', function (): void {
    $this->recruiter->emailTemplates()->createMany([
        ['key' => EmailTemplate::INVITATION, 'subject' => 'On se voit ?', 'body' => 'Coucou {candidate_name}'],
        ['key' => EmailTemplate::REFUSAL, 'subject' => 'Dommage', 'body' => 'Bonjour {candidate_name}, non.'],
    ]);

    $interview = Interview::factory()->create([
        'application_id' => $this->application->id,
        'created_by' => $this->recruiter->id,
        'starts_at' => '2026-10-01 10:00:00',
    ]);

    $invitation = new InterviewInvitationMail($interview);
    expect($invitation->envelope()->subject)->toBe('On se voit ?');
    expect($invitation->render())->toContain('Coucou Jane Doe');

    $refusal = new CandidateRejectedMail($this->application, 'Dommage pour toi.');
    expect($refusal->envelope()->subject)->toBe('Dommage');
    expect($refusal->render())->toContain('Bonjour Jane Doe, non.')->toContain('Dommage pour toi.');
});

it('rejects unknown template keys', function (): void {
    $token = $this->recruiter->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/settings/email-templates/nope', [
            'subject' => 'x',
            'body' => 'y',
        ])
        ->assertNotFound();
});
