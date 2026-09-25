<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

class ReadableDatesCheck implements AtsCheck
{
    private const DATE = '(?:(?:0[1-9]|1[0-2])\/(?:19|20)\d{2}|(?:janv?|f[ée]vr?|fevr?|mars?|avril|avr?|mai|juin?|juil(?:let)?|ao[uû]t|sept?|oct(?:obre)?|nov(?:embre)?|d[ée]c(?:embre)?|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|january|february|march|april|june|july|august|september|october|november|december)\.?\s+(?:19|20)\d{2})';

    private const PERIOD = '/(?:'
        .'(?:'.self::DATE.'\s*[-–—]\s*(?:'.self::DATE.'|[Pp]résent|[Pp]resent|[Aa]ujourd))'
        .'|'.self::DATE
        .'|\b(?:19|20)\d{2}\s*[-–—]\s*(?:19|20)\d{2}\b'
        .')/u';

    public function code(): string
    {
        return 'ATS-05';
    }

    public function evaluate(AtsContext $context): AtsCheckResult
    {
        $count = preg_match_all(self::PERIOD, $context->text);
        $count = is_int($count) ? $count : 0;

        return match (true) {
            $count >= 2 => new AtsCheckResult(
                $this->code(),
                10,
                10,
                "{$count} périodes datées reconnues.",
                '',
            ),
            $count === 1 => new AtsCheckResult(
                $this->code(),
                5,
                10,
                'Une seule période datée reconnue.',
                'Indiquez les dates (MM/AAAA ou « Jan 2022 – Présent ») de vos expériences.',
            ),
            default => new AtsCheckResult(
                $this->code(),
                0,
                10,
                'Aucune date lisible détectée.',
                'Ajoutez des dates de début/fin lisibles à chaque expérience.',
            ),
        };
    }
}
