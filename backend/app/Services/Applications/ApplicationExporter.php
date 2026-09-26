<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * EF-1203 / RG-14: one-click export of a complete candidate file as a ZIP
 * containing the structured data (donnees.json) plus every uploaded file
 * (CV and attachments) exactly as stored on the private disk.
 */
class ApplicationExporter
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function export(Application $application, ?User $actor = null, ?string $ip = null): string
    {
        $application->loadMissing(['offer:id,title', 'analysis', 'skills', 'events']);

        // ponytail: reuse the tempnam() file itself — appending '.zip' orphaned it
        $tmpPath = tempnam(sys_get_temp_dir(), 'export-');
        $zip = new ZipArchive;

        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP impossible à ouvrir.');
        }

        $zip->addFromString(
            'donnees.json',
            json_encode($this->payload($application), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->addFile($zip, $application->cv_path, 'cv/'.($application->cv_original_name ?: 'cv.pdf'));

        foreach ($application->files ?? [] as $index => $file) {
            $path = $file['path'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $original = $file['original_name'] ?? null;
            $name = is_string($original) && $original !== '' ? $original : 'fichier-'.($index + 1);

            $this->addFile($zip, $path, 'fichiers/'.$name);
        }

        $zip->close();

        $this->audit->log($actor, AuditAction::ApplicationExported, $application, ['format' => 'zip'], $ip);

        return $tmpPath;
    }

    private function addFile(ZipArchive $zip, ?string $path, string $entry): void
    {
        if ($path === null || $path === '' || ! Storage::disk('private')->exists($path)) {
            return;
        }

        $zip->addFile(Storage::disk('private')->path($path), $entry);
    }

    /** @return array<string, mixed> */
    private function payload(Application $application): array
    {
        return [
            'id' => $application->id,
            'offer' => ['id' => $application->offer?->id, 'title' => $application->offer?->title],
            'full_name' => $application->full_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'status' => $application->status->value,
            'rating' => $application->rating,
            'match_score' => $application->analysis?->match_score,
            'ats_verdict' => $application->analysis?->ats_verdict?->value,
            'answers' => $application->answers,
            'skills' => $application->skills->pluck('name'),
            'consent_at' => $application->consent_at?->toIso8601String(),
            'consent_version' => $application->consent_version,
            'created_at' => $application->created_at?->toIso8601String(),
            'events' => $application->events->map(fn ($event) => [
                'type' => $event->type->value,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
        ];
    }
}
