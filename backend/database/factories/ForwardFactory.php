<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ForwardDelivery;
use App\Enums\ForwardStatus;
use App\Models\Forward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Forward>
 */
class ForwardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'to_emails' => ['client@example.com'],
            'subject' => 'Candidatures pour le poste Dev Laravel',
            'message' => 'Bonjour, veuillez trouver nos candidats.',
            'delivery' => ForwardDelivery::Attachments,
            'include_analysis' => false,
            'status' => ForwardStatus::Queued,
        ];
    }
}
