<?php

declare(strict_types=1);

namespace App\Services\Applications;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile verification (EF-409). Pass-through when no secret
 * is configured (local/dev): spam is then covered by throttling + honeypot.
 */
class CaptchaVerifier
{
    public function verify(?string $token, string $ip): bool
    {
        $secret = (string) config('services.turnstile.secret', '');

        if ($secret === '' || $token === null || $token === '') {
            return $secret === '';
        }

        try {
            $response = Http::timeout(10)->asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                ['secret' => $secret, 'response' => $token, 'remoteip' => $ip]
            );

            return (bool) ($response->json('success', false));
        } catch (\Throwable) {
            return false;
        }
    }
}
