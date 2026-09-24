<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\WorkMode;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->jobTitle(),
            'type' => fake()->randomElement(OfferType::cases()),
            'description' => fake()->paragraphs(2, true),
            'missions' => fake()->paragraph(),
            'profile_wanted' => fake()->paragraph(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'work_mode' => fake()->randomElement(WorkMode::cases()),
            'positions_count' => 1,
            'required_skills' => ['php', 'laravel'],
            'preferred_skills' => ['docker'],
            'min_experience_years' => 2.0,
            'languages' => [['name' => 'français', 'level' => 'courant']],
            'knockout_criteria' => [],
            'criteria_version' => 1,
            'status' => OfferStatus::Draft,
        ];
    }
}
