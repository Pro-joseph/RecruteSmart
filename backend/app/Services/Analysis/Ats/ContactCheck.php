<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

use App\Services\Analysis\PhoneMatcher;

class ContactCheck implements AtsCheck
{
    public function code(): string
    {
        return 'ATS-03';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $hasEmail = preg_match('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', $context->text) === 1;
        $hasPhone = PhoneMatcher::matches($context->text) !== [];

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
}
