<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'type' => $offer->type->value,
            'type_label' => $offer->type_label,
            'description' => $offer->description,
            'missions' => $offer->missions,
            'profile_wanted' => $offer->profile_wanted,
            'city' => $offer->city,
            'country' => $offer->country,
            'work_mode' => $offer->work_mode?->value,
            'salary_min' => $offer->salary_min,
            'salary_max' => $offer->salary_max,
            'salary_currency' => $offer->salary_currency,
            'positions_count' => $offer->positions_count,
            'required_skills' => $offer->required_skills ?? [],
            'preferred_skills' => $offer->preferred_skills ?? [],
            'min_experience_years' => $offer->min_experience_years,
            'education_level' => $offer->education_level,
            'languages' => $offer->languages ?? [],
            'knockout_criteria' => $offer->knockout_criteria ?? [],
            'scoring_weights' => $offer->scoring_weights,
            'criteria_version' => $offer->criteria_version,
            'status' => $offer->status->value,
            'public_token' => $this->when($offer->public_token !== null, $offer->public_token),
            'public_url' => $this->when(
                $offer->public_token !== null,
                fn (): string => rtrim((string) config('app.frontend_url'), '/').'/apply/'.$offer->public_token
            ),
            'deadline_at' => $offer->deadline_at?->toIso8601String(),
            'published_at' => $offer->published_at?->toIso8601String(),
            'closed_at' => $offer->closed_at?->toIso8601String(),
            'created_at' => $offer->created_at?->toIso8601String(),
            'updated_at' => $offer->updated_at?->toIso8601String(),
            'applications_count' => $this->whenCounted('applications'),
            'new_applications_count' => $this->whenCounted('new_applications_count'),
            'form_fields' => OfferFormFieldResource::collection($this->whenLoaded('formFields')),
        ];
    }
}
