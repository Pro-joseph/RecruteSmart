<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Detects phone-number-like candidates while ignoring year ranges ("2020 - 2023").
 */
final class PhoneMatcher
{
    /**
     * @return list<string> exact substrings that look like phone numbers
     */
    public static function matches(string $text): array
    {
        $count = preg_match_all('/\+?\d[\d\s().\-–]{6,}\d/u', $text, $matches);
        if ($count === 0 || $count === false) {
            return [];
        }

        $found = [];
        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?? '';
            $digitCount = strlen($digits);

            if ($digitCount < 8 || $digitCount > 15) {
                continue;
            }

            if (preg_match('/^(?:19|20)\d{2}\s*[-–]\s*(?:19|20)\d{2}$/', trim($candidate)) === 1) {
                continue;
            }

            $found[] = $candidate;
        }

        return $found;
    }
}
