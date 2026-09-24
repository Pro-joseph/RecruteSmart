<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Application;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'new',
            'answers' => [],
            'cv_path' => 'applications/1/'.fake()->uuid().'.pdf',
            'cv_original_name' => 'cv.pdf',
            'cv_mime' => 'application/pdf',
            'cv_size' => 102400,
            'consent_at' => now(),
            'consent_version' => 'v1',
        ];
    }
}
