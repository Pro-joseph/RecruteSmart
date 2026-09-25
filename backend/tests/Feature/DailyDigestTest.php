<?php

declare(strict_types=1);

use App\Mail\DailyDigestMail;
use App\Models\Application;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    $this->owner = User::factory()->create();
    $this->offer = Offer::factory()->create(['user_id' => $this->owner->id]);
});

it('sends one digest per recruiter covering the last 24 hours (EF-1102)', function (): void {
    Application::factory()->count(2)->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subHours(3),
    ]);
    Application::factory()->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('notifications:daily-digest')
        ->expectsOutput('1 digest(s) sent.')
        ->assertSuccessful();

    Mail::assertSent(DailyDigestMail::class, 1);
    Mail::assertSent(DailyDigestMail::class, function ($mail): bool {
        return $mail->total === 2
            && $mail->offers[0]['offer_id'] === $this->offer->id
            && str_contains($mail->envelope()->subject, '2 nouvelles candidatures')
            && $mail->hasTo($this->owner->email);
    });
});

it('sends nothing when no application arrived (EF-1102)', function (): void {
    Application::factory()->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('notifications:daily-digest')
        ->expectsOutput('No new application in the last 24 hours.')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

it('supports a dry run that sends nothing (EF-1102)', function (): void {
    Application::factory()->create([
        'offer_id' => $this->offer->id,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('notifications:daily-digest --dry-run')->assertSuccessful();

    Mail::assertNothingSent();
});
