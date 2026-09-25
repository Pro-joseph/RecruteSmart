<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Interview $interview */
        $interview = $this->resource;

        return [
            'id' => $interview->id,
            'application_id' => $interview->application_id,
            'type' => $interview->type->value,
            'starts_at' => $interview->starts_at->toIso8601String(),
            'duration_minutes' => $interview->duration_minutes,
            'location_or_link' => $interview->location_or_link,
            'participants' => $interview->participants ?? [],
            'status' => $interview->status->value,
            'notes' => $interview->notes,
            'decision' => $interview->decision?->value,
            'invitation_sent_at' => $interview->invitation_sent_at?->toIso8601String(),
            'created_at' => $interview->created_at?->toIso8601String(),
        ];
    }
}
