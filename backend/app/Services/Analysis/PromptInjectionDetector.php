<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Detects prompt-injection motifs in CV text (spec §6.8).
 * Matches are recorded as analysis anomalies and shown to the recruiter.
 */
class PromptInjectionDetector
{
    /**
     * @var array<string, string> label => pattern
     */
    private const PATTERNS = [
        'ignore_instructions' => '/ignore\s+(?:all\s+|previous\s+|prior\s+|les\s+|toutes\s+les\s+)*instructions?/iu',
        'give_100_percent' => '/(?:donnez?|donnes|give|mettez?)\s+(?:moi\s+)?(?:un\s+)?100\s*%?(?:\s|,|\.|$)/iu',
        'system_prompt_override' => '/(?:systeme|system)\s*prompt|r[ôo]le\s+d\s*assistant|tu\s+es\s+d[ée]sormais/iu',
        'white_on_white' => '/(?:color|couleur)\s*:\s*(?:#fff(?:f)?|white|blanc)|font-size\s*:\s*0(?:px)?\b/iu',
        'reveal_prompt' => '/(?:montre|reveal|print|display)\s+(?:moi\s+)?(?:ton|your|le)\s+(?:prompt|systeme|instructions)/iu',
    ];

    /**
     * @return list<string> labels of detected motifs (empty when clean)
     */
    public function detect(string $text): array
    {
        $found = [];
        foreach (self::PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
