<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

class ContactCheck implements AtsCheck
{
    public function code(): string
    {
        return 'ATS-03';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $hasEmail = preg_match('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', $context->text) === 1;
        $hasPhone = $this->hasPhone($context->text);

        $earned = ($hasEmail ? 5 : 0) + ($hasPhone ? 5 : 0);

        $missing = [];
        if (! $hasEmail) {
            $missing[] = 'email';
        }
        if (! $hasPhone) {
            $missing[] = 'téléphone';
        }

        if ($missing === []) {
            return new AtsCheckResult($this->code(), 10, 10, 'Email et téléphone présents.', '');
        }

        return new AtsCheckResult(
            $this->code(),
            $earned,
            10,
            'Coordonnée(s) manquante(s) : '.implode(', ', $missing).'.',
            'Indiquez votre email et votre numéro de téléphone en tête de CV.',
        );
    }

    private function hasPhone(string $text): bool
    {
        $count = preg_match_all('/\+?\d[\d\s().\-–]{6,}\d/u', $text, $matches);
        if ($count === 0 || $count === false) {
            return false;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';
            $digitCount = strlen($digits);

            if ($digitCount < 8 || $digitCount > 15) {
                continue;
            }

            // Year ranges like "2020 - 2023" are not phone numbers.
            if (preg_match('/^(?:19|20)\d{2}\s*[-–]\s*(?:19|20)\d{2}$/', trim($candidate)) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }
}
