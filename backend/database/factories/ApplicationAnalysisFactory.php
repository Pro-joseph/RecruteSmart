<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationAnalysis>
 */
class ApplicationAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'status' => 'pending',
            'criteria_version' => 1,
            'prompt_version' => 'v1',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'ats_score' => 80,
            'ats_verdict' => 'compliant',
            'ats_checks' => [],
            'match_score' => 75,
            'match_breakdown' => [],
            'summary' => 'Profil pertinent.',
            'analyzed_at' => now(),
        ]);
    }
}
