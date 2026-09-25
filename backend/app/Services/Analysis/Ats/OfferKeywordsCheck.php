<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

use App\Services\Analysis\SkillAliases;

class OfferKeywordsCheck implements AtsCheck
{
    public function code(): string
    {
        return 'ATS-06';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $total = count($context->requiredSkills);

        if ($total === 0) {
            return new AtsCheckResult(
                $this->code(),
                25,
                25,
                'Aucune compétence obligatoire dans l’offre : contrôle sans objet.',
                '',
            );
        }

        $text = mb_strtolower($context->text);
        $matched = [];
        $missing = [];

        foreach ($context->requiredSkills as $skill) {
            $found = false;
            foreach (SkillAliases::variants($skill) as $variant) {
                if ($variant !== '' && $this->containsWord($text, $variant)) {
                    $found = true;
                    break;
                }
            }

            $found ? $matched[] = $skill : $missing[] = $skill;
        }

        $earned = (int) round(25 * count($matched) / $total);

        if ($missing === []) {
            return new AtsCheckResult(
                $this->code(),
                $earned,
                25,
                'Toutes les compétences obligatoires sont présentes : '.implode(', ', $matched).'.',
                '',
            );
        }

        return new AtsCheckResult(
            $this->code(),
            $earned,
            25,
            count($matched).' compétence(s) sur '.$total.' retrouvée(s) dans le CV. Manquantes : '.implode(', ', $missing).'.',
            'Mettez en avant les compétences clés de l’offre dans votre CV.',
        );
    }

    private function containsWord(string $textLower, string $word): bool
    {
        $quoted = preg_quote($word, '/');

        return preg_match('/(?<![\pL\pN_])'.$quoted.'(?![\pL\pN_])/u', $textLower) === 1;
    }
}
