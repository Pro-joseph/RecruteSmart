<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets the baseline security headers on every response (ENF-04)', function (): void {
    $response = $this->getJson('/api/v1/public/offers/does-not-exist');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("script-src 'self'");
});

it('does not advertise HSTS outside production HTTPS (ENF-04)', function (): void {
    $response = $this->getJson('/api/v1/public/offers/does-not-exist');

    $response->assertHeaderMissing('Strict-Transport-Security');
});

it('sets the security headers on authenticated responses too (ENF-04)', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/api/v1/auth/me');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
