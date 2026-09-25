<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

use App\Enums\AtsVerdict;

/**
 * Sums the six ATS checks (Part I §5.2) and derives the verdict.
 */
class AtsScorer
{
    /** @var list<AtsCheck> */
    private readonly array $checks;

    public function __construct()
    {
        $this->checks = [
            new ExtractableTextCheck,
            new StandardSectionsCheck,
            new ContactCheck,
            new FormatLengthCheck,
            new ReadableDatesCheck,
            new OfferKeywordsCheck,
        ];
    }

    /**
     * @return array{score: int, verdict: AtsVerdict, checks: list<array<string, mixed>>}
     */
    public function score(AtsContext $context): array
    {
        $score = 0;
        $results = [];

        foreach ($this->checks as $check) {
            $result = $check->evaluate($context);
            $score += $result->earned;
            $results[] = $result->toArray();
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'verdict' => self::verdictFor($score),
            'checks' => $results,
        ];
    }

    public static function verdictFor(int $score): AtsVerdict
    {
        return match (true) {
            $score >= 75 => AtsVerdict::Conforming,
            $score >= 50 => AtsVerdict::NeedsImprovement,
            default => AtsVerdict::NonCompliant,
        };
    }
}
