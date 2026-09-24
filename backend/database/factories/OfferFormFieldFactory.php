<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FieldType;
use App\Models\Offer;
use App\Models\OfferFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferFormField>
 */
class OfferFormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'key' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'type' => FieldType::Text,
            'is_required' => false,
            'is_locked' => false,
            'is_sensitive' => false,
            'is_hidden' => false,
            'position' => 0,
        ];
    }
}
