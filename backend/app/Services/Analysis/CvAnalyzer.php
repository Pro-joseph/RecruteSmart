<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Analyzes a sanitized CV against an offer and returns the validated payload
 * (spec §6.2 step 6). Implementations: LlmCvAnalyzer, FakeCvAnalyzer.
 */
interface CvAnalyzer
{
    /**
     * @param  array<string, mixed>  $offerContext  offer title/description/criteria
     *
     * @throws InvalidAnalysisOutputException
     * @throws ProviderException
     */
    public function analyze(string $cvText, array $offerContext): LlmAnalysisResult;
}
