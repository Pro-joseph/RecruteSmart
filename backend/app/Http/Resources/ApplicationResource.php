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

        $analysis = $application->relationLoaded('analysis') ? $application->analysis : null;
        $offer = $application->relationLoaded('offer') ? $application->offer : null;
        $skills = $application->relationLoaded('skills')
            ? $application->skills->pluck('slug')->values()->all()
            : [];

        return [
            'id' => $application->id,
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'status' => $application->status->value,
            'rating' => $application->rating,
            'created_at' => $application->created_at?->toIso8601String(),
            'analysis' => $analysis === null ? null : [
                'status' => $analysis->status->value,
                'match_score' => $analysis->match_score,
                'ats_score' => $analysis->ats_score,
                'ats_verdict' => $analysis->ats_verdict?->value,
                'years_experience' => $analysis->years_experience !== null ? (float) $analysis->years_experience : null,
                'city' => $analysis->city,
                'skills' => $skills,
                'is_stale' => $offer !== null && $analysis->criteria_version < $offer->criteria_version,
                'error_code' => $analysis->error_code,
                'error_message' => $analysis->error_message,
            ],
        ];
    }
}
