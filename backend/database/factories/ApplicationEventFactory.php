<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApplicationEventType;
use App\Models\Application;
use App\Models\ApplicationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationEvent>
 */
class ApplicationEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'user_id' => null,
            'type' => ApplicationEventType::StatusChanged,
            'payload' => [],
        ];
    }
}
