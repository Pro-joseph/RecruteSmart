<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

class StandardSectionsCheck implements AtsCheck
{
    /**
     * Section heading patterns (FR/EN), line-anchored.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const SECTIONS = [
        'expérience' => ['/^\s*[-•*]?\s*(?:exp[ée]riences?\b|professional experience\b|work experience\b|parcours (?:pro|professionnel)\b)/imu', 8],
        'formation' => ['/^\s*[-•*]?\s*(?:formations?\b|[ée]tudes\b|education\b|academic background\b|dipl[oô]mes?\b|cursus\b)/imu', 6],
        'compétences' => ['/^\s*[-•*]?\s*(?:comp[ée]tences?\b|skills?\b|technologies\b|stack\b|connaissances\b)/imu', 6],
    ];

    public function code(): string
    {
        return 'ATS-02';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $found = [];
        $earned = 0;

        foreach (self::SECTIONS as $name => [$pattern, $points]) {
            if (preg_match($pattern, $context->text) === 1) {
                $found[] = $name;
                $earned += $points;
            }
        }

        $missing = array_diff(array_keys(self::SECTIONS), $found);

        if ($missing === []) {
            return new AtsCheckResult($this->code(), $earned, 20, 'Sections standard détectées : '.implode(', ', $found).'.', '');
        }

        return new AtsCheckResult(
            $this->code(),
            $earned,
            20,
            'Section(s) manquante(s) : '.implode(', ', $missing).'.',
            'Ajoutez des titres de sections clairs : Expérience, Formation, Compétences.',
        );
    }
}
