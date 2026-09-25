<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

class FormatLengthCheck implements AtsCheck
{
    public function code(): string
    {
        return 'ATS-04';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $validFormat = str_contains($context->mime, 'pdf')
            || str_contains($context->mime, 'wordprocessingml.document')
            || str_contains($context->mime, 'docx');

        $pages = $context->pages;
        $validLength = $pages !== null && $pages >= 1 && $pages <= 3;

        $earned = ($validFormat ? 5 : 0) + ($validLength ? 5 : 0);
        $parts = [];

        if (! $validFormat) {
            $parts[] = 'format non standard ('.$context->mime.')';
        }

        if (! $validLength) {
            $parts[] = $pages === null ? 'nombre de pages inconnu' : "{$pages} pages (1 à 3 attendues)";
        }

        if ($parts === []) {
            return new AtsCheckResult($this->code(), 10, 10, 'Format PDF/DOCX et longueur conforme (1 à 3 pages).', '');
        }

        return new AtsCheckResult(
            $this->code(),
            $earned,
            10,
            'Format/longueur non conforme : '.implode(', ', $parts).'.',
            'Utilisez un PDF ou DOCX de 1 à 3 pages.',
        );
    }
}
