<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => ApplicationFactory::new(),
            'created_by' => User::factory(),
            'type' => InterviewType::Video,
            'starts_at' => now()->addDay(),
            'duration_minutes' => 30,
            'location_or_link' => 'https://meet.example.com/abc',
            'participants' => ['recruteur@example.com'],
            'status' => InterviewStatus::Planned,
        ];
    }
}
