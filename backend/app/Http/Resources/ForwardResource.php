<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Forward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Forward
 */
class ForwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Forward $forward */
        $forward = $this->resource;

        return [
            'id' => $forward->id,
            'to_emails' => $forward->to_emails,
            'subject' => $forward->subject,
            'message' => $forward->message,
            'delivery' => $forward->delivery->value,
            'include_analysis' => $forward->include_analysis,
            'status' => $forward->status->value,
            'error_message' => $forward->error_message,
            'sent_at' => $forward->sent_at?->toIso8601String(),
            'created_at' => $forward->created_at?->toIso8601String(),
            'candidates' => $forward->snapshots->map(static fn ($snapshot): array => [
                'application_id' => $snapshot->application_id,
                'name' => $snapshot->candidate_name_snapshot,
            ])->values()->all(),
        ];
    }
}
