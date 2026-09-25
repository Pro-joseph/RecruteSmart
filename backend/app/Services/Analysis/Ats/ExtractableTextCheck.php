<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

class ExtractableTextCheck implements AtsCheck
{
    public function code(): string
    {
        return 'ATS-01';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $length = mb_strlen($context->text);
        $readableRatio = $this->readableRatio($context->text);
        $ok = $length >= 200 && $readableRatio >= 0.9;

        if ($ok) {
            return new AtsCheckResult(
                code: $this->code(),
                earned: 25,
                max: 25,
                message: "Texte extractible ({$length} caractères, ".round($readableRatio * 100).' % lisibles).',
                advice: '',
            );
        }

        $message = $length < 200
            ? "Texte insuffisant ({$length} caractères, 200 requis)."
            : 'Texte peu lisible ('.round($readableRatio * 100).' % de caractères valides, 90 % requis).';

        return new AtsCheckResult(
            code: $this->code(),
            earned: 0,
            max: 25,
            message: $message.' Vérifiez qu’il ne s’agit pas d’un CV scanné ou d’une image.',
            advice: 'Déposez un CV au format texte (PDF natif ou DOCX), sans scan ni capture d’écran.',
        );
    }

    private function readableRatio(string $text): float
    {
        $total = mb_strlen($text);
        if ($total === 0) {
            return 0.0;
        }

        $readable = preg_match_all('/[^\p{Cc}]/u', $text);
        $readable = is_int($readable) ? $readable : 0;
        $replacementChars = substr_count($text, "\u{FFFD}");

        return max(0, $readable - $replacementChars) / $total;
    }
}
