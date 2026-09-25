<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Offer;

/**
 * Computes match_score from LLM sub-scores (Part I §5.3) — pure, never trusts
 * a score computed by the LLM. Criteria are renormalized to those present
 * in the offer (RG-08).
 */
class ScoreCalculator
{
    public const CRITERIA = ['required_skills', 'preferred_skills', 'experience', 'education', 'languages'];

    /**
     * @param  array<string, mixed>  $payload  validated LLM payload
     * @return array{
     *     match_score: int|null,
     *     match_breakdown: array<string, array<string, mixed>>,
     *     knockout_flags: array<string, bool>
     * }
     */
    public function calculate(Offer $offer, array $payload): array
    {
        /** @var array<string, int> $weights */
        $weights = $offer->scoring_weights ?: config('recruitment.scoring_weights');
        $knockout = array_values(array_intersect($offer->knockout_criteria ?? [], self::CRITERIA));
        $threshold = (int) config('recruitment.knockout_threshold', 40);
        $llmCriteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

        $sum = 0.0;
        $weightSum = 0;
        $breakdown = [];
        $knockoutFlags = [];

        foreach ($this->activeCriteria($offer) as $key) {
            $sub = $llmCriteria[$key]['score'] ?? null;
            $weight = (int) ($weights[$key] ?? 0);

            if (! is_numeric($sub) || $weight <= 0) {
                continue;
            }

            $sub = max(0, min(100, (int) $sub));
            $sum += $weight * $sub;
            $weightSum += $weight;

            $breakdown[$key] = [
                'score' => $sub,
                'weight' => $weight,
                'evidence' => (string) ($llmCriteria[$key]['evidence'] ?? ''),
            ];

            if (in_array($key, $knockout, true) && $sub < $threshold) {
                $knockoutFlags[$key] = true;
            }
        }

        $matchScore = $weightSum > 0 ? (int) round($sum / $weightSum) : null;

        return [
            'match_score' => $matchScore,
            'match_breakdown' => $matchScore === null ? null : $breakdown,
            'knockout_flags' => $matchScore === null ? null : $knockoutFlags,
        ];
    }

    /**
     * Criteria declared in the offer (RG-08: only those enter the calculation).
     *
     * @return list<string>
     */
    public function activeCriteria(Offer $offer): array
    {
        $active = [];

        if (($offer->required_skills ?? []) !== []) {
            $active[] = 'required_skills';
        }
        if (($offer->preferred_skills ?? []) !== []) {
            $active[] = 'preferred_skills';
        }
        if ($offer->min_experience_years !== null) {
            $active[] = 'experience';
        }
        if (($offer->education_level ?? '') !== '' && $offer->education_level !== null) {
            $active[] = 'education';
        }
        if (($offer->languages ?? []) !== []) {
            $active[] = 'languages';
        }

        return $active;
    }
}
