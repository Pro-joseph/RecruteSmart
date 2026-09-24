<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        return [
            'id' => $application->id,
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'status' => $application->status->value,
            'rating' => $application->rating,
            'created_at' => $application->created_at?->toIso8601String(),
        ];
    }
}
