<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FieldType;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;
        $offer = $application->offer;
        $analysis = $application->analysis;

        return [
            'id' => $application->id,
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'status' => $application->status->value,
            'rating' => $application->rating,
            'created_at' => $application->created_at?->toIso8601String(),
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
            ],
            'answers' => $this->answers($application),
            'files' => $this->files($application),
            'analysis' => $analysis === null ? null : [
                'status' => $analysis->status->value,
                'match_score' => $analysis->match_score,
                'match_breakdown' => $analysis->match_breakdown,
                'knockout_flags' => $analysis->knockout_flags,
                'ats_score' => $analysis->ats_score,
                'ats_verdict' => $analysis->ats_verdict?->value,
                'ats_checks' => $analysis->ats_checks,
                'summary' => $analysis->summary,
                'strengths' => $analysis->strengths,
                'gaps' => $analysis->gaps,
                'anomalies' => $analysis->anomalies,
                'years_experience' => $analysis->years_experience !== null ? (float) $analysis->years_experience : null,
                'city' => $analysis->city,
                'country' => $analysis->country,
                'skills' => $application->skills->pluck('slug')->values()->all(),
                'is_stale' => $analysis->criteria_version < $offer->criteria_version,
                'error_code' => $analysis->error_code,
                'error_message' => $analysis->error_message,
                'analyzed_at' => $analysis->analyzed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Form answers merged with their field configuration (EF-701).
     *
     * @return list<array<string, mixed>>
     */
    private function answers(Application $application): array
    {
        $answers = $application->answers ?? [];
        $rows = [];

        foreach ($application->offer->formFields->sortBy('position') as $field) {
            if ($field->type === FieldType::File || $field->type === FieldType::Image) {
                continue;
            }

            $value = match ($field->key) {
                'full_name' => $application->full_name,
                'email' => $application->email,
                default => $answers[$field->key] ?? null,
            };

            $rows[] = [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'value' => $value,
            ];
        }

        foreach ($answers as $key => $value) {
            if (! in_array($key, array_column($rows, 'key'), true)) {
                $rows[] = [
                    'key' => $key,
                    'label' => (string) $key,
                    'type' => 'text',
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }

    /**
     * CV plus extra uploaded files, each pointing at the authenticated
     * download route (spec: short-lived access, no public URL).
     *
     * @return list<array<string, mixed>>
     */
    private function files(Application $application): array
    {
        $rows = [[
            'key' => 'cv',
            'name' => $application->cv_original_name,
            'mime' => $application->cv_mime,
            'size' => $application->cv_size,
            'url' => "/api/v1/applications/{$application->id}/files/cv",
        ]];

        foreach ($application->files ?? [] as $file) {
            $rows[] = [
                'key' => (string) ($file['key'] ?? ''),
                'name' => (string) ($file['original_name'] ?? ''),
                'mime' => (string) ($file['mime'] ?? ''),
                'size' => (int) ($file['size'] ?? 0),
                'url' => "/api/v1/applications/{$application->id}/files/".($file['key'] ?? ''),
            ];
        }

        return $rows;
    }
}
