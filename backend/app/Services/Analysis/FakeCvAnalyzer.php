<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Deterministic analyzer for tests: returns a schema-shaped payload
 * built from the offer context, or throws when configured to fail.
 */
class FakeCvAnalyzer implements CvAnalyzer
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        private readonly ?array $payload = null,
        private readonly ?string $failWith = null,
    ) {}

    public function analyze(string $cvText, array $offerContext): LlmAnalysisResult
    {
        if ($this->failWith !== null) {
            throw new InvalidAnalysisOutputException($this->failWith);
        }

        return new LlmAnalysisResult(
            payload: $this->payload ?? self::sample($offerContext),
            tokensIn: 100,
            tokensOut: 120,
            model: 'fake-model',
            provider: 'fake',
        );
    }

    /**
     * @param  array<string, mixed>  $offerContext
     * @return array<string, mixed>
     */
    public static function sample(array $offerContext): array
    {
        $criteria = [];

        $required = $offerContext['required_skills'] ?? [];
        if (is_array($required) && $required !== []) {
            $criteria['required_skills'] = [
                'score' => 85,
                'evidence' => 'Compétences principales retrouvées dans l\'expérience.',
                'matched' => array_slice($required, 0, 2),
                'missing' => array_slice($required, 2),
            ];
        }

        $preferred = $offerContext['preferred_skills'] ?? [];
        if (is_array($preferred) && $preferred !== []) {
            $criteria['preferred_skills'] = [
                'score' => 50,
                'evidence' => 'Partiellement couvert.',
                'matched' => array_slice($preferred, 0, 1),
                'missing' => array_slice($preferred, 1),
            ];
        }

        if (isset($offerContext['min_experience_years'])) {
            $criteria['experience'] = [
                'score' => 70,
                'evidence' => 'Expérience proche du minimum demandé.',
            ];
        }

        if (! empty($offerContext['education_level'])) {
            $criteria['education'] = [
                'score' => 100,
                'evidence' => 'Formation conforme.',
            ];
        }

        $languages = $offerContext['languages'] ?? [];
        if (is_array($languages) && $languages !== []) {
            $criteria['languages'] = [
                'score' => 60,
                'evidence' => 'Langues partiellement conformes.',
            ];
        }

        if ($criteria === []) {
            $criteria['experience'] = ['score' => 70, 'evidence' => 'Aucun critère fourni.'];
        }

        return [
            'years_experience_total' => 4.0,
            'location' => ['city' => 'Casablanca', 'country' => 'Maroc'],
            'skills' => array_values(array_unique(array_merge(
                array_map('strval', $required ?? []),
                ['php', 'laravel'],
            ))),
            'languages' => [['name' => 'français', 'level' => 'courant']],
            'education' => [['degree' => 'Licence', 'field' => 'Informatique', 'institution' => 'Université', 'year' => 2020]],
            'recent_positions' => [['title' => 'Développeur backend', 'company' => 'ACME', 'months' => 24]],
            'criteria' => $criteria,
            'summary' => 'Profil backend solide, à creuser sur l\'infrastructure.',
            'strengths' => ['Maîtrise de Laravel', 'Expérience en API'],
            'gaps' => ['Redis non mentionné'],
            'anomalies' => [],
        ];
    }
}
