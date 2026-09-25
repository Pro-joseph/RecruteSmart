<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Illuminate\Support\Str;

/**
 * Skill + city normalization (spec §6.6): lowercase, alias table,
 * punctuation stripping, dedupe; accent-free city slug.
 */
class SkillNormalizer
{
    private const PUNCTUATION = ',;:!?()[]"\'/\\|@&%$£€*';

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /**
     * @param  list<string>  $skills
     * @return list<array{name: string, slug: string}> unique, normalized
     */
    public function normalizeSkills(array $skills): array
    {
        $unique = [];

        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $name = SkillAliases::canonical($this->stripPunctuation($skill));
            if ($name === '') {
                continue;
            }

            $slug = $this->slug($name);
            if ($slug === '') {
                continue;
            }

            $unique[$slug] = ['name' => $name, 'slug' => $slug];
        }

        return array_values($unique);
    }

    /**
     * Accent-free, lowercase, trimmed city name for indexing.
     */
    public function normalizeCity(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        $lower = mb_strtolower(trim($city));
        $flat = strtr($lower, self::ACCENTS);
        $flat = preg_replace('/\s+/u', ' ', $flat) ?? $flat;

        return trim($flat);
    }

    private function stripPunctuation(string $value): string
    {
        return str_replace(str_split(self::PUNCTUATION), '', $value);
    }

    private function slug(string $name): string
    {
        $withWords = str_replace(['+', '#'], ['plus', 'sharp'], $name);

        return Str::slug($withWords);
    }
}
