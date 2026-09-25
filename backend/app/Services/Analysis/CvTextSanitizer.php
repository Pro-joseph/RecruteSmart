<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Prepares CV text for the LLM (spec §6.2 step 5, §6.8 EF-610):
 * masks direct contact data, replaces the candidate's name with [CANDIDAT],
 * drops sensitive lines (age, DOB, nationality, marital status, photo),
 * truncates to LLM_MAX_INPUT_CHARS.
 */
class CvTextSanitizer
{
    private const SENSITIVE_LINE = '/(?:'
        .'\b\d{1,3}\s*ans\b'
        .'|\b[âa]ge\s*:'
        .'|date\s+de\s+naissance'
        .'|\bn[ée]e?\s+(?:le|on)\s+\d'
        .'|birthday'
        .'|date\s+of\s+birth'
        .'|nationalit[ée]'
        .'|\bnationality\b'
        .'|situation\s+familiale'
        .'|\b[ée]tat\s+civil\b'
        .'|\bmari[ée]e?\b'
        .'|\bc[ée]libataire\b'
        .'|\bdivorc[ée]e?\b'
        .'|\bphoto\b'
        .')/iu';

    public function sanitize(string $text, string $candidateName): string
    {
        $text = $this->maskUrls($text);
        $text = $this->maskEmails($text);
        $text = $this->maskPhones($text);
        $text = $this->replaceName($text, $candidateName);
        $text = $this->dropSensitiveLines($text);

        return $this->truncate($text);
    }

    private function maskUrls(string $text): string
    {
        return preg_replace('/\b(?:https?:\/\/|www\.)[^\s<>"\']+/iu', '[URL]', $text) ?? $text;
    }

    private function maskEmails(string $text): string
    {
        return preg_replace('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', '[EMAIL]', $text) ?? $text;
    }

    private function maskPhones(string $text): string
    {
        foreach (PhoneMatcher::matches($text) as $phone) {
            $text = str_replace($phone, '[TEL]', $text);
        }

        return $text;
    }

    private function replaceName(string $text, string $candidateName): string
    {
        $name = trim($candidateName);
        if ($name === '') {
            return $text;
        }

        $quoted = preg_quote($name, '/');

        return preg_replace('/(?<![\pL\pN_])'.$quoted.'(?![\pL\pN_])/iu', '[CANDIDAT]', $text) ?? $text;
    }

    private function dropSensitiveLines(string $text): string
    {
        $kept = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match(self::SENSITIVE_LINE, $line) === 1) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    private function truncate(string $text): string
    {
        $limit = (int) config('llm.max_input_chars', 30000);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $lastNewline = mb_strrpos($cut, "\n");

        return $lastNewline !== false && $lastNewline > $limit * 0.8
            ? mb_substr($cut, 0, $lastNewline)
            : $cut;
    }
}
