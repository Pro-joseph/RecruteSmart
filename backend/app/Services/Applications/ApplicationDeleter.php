<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ForwardApplication;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * EF-1201 / RG-13: definitive deletion — database rows, private files and
 * the forward history snapshot (« Candidat supprimé »), shared by the manual
 * DELETE route and the retention purge (EF-1202).
 */
class ApplicationDeleter
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function delete(Application $application, ?User $actor = null, ?string $ip = null, bool $purged = false): void
    {
        $paths = $this->paths($application);

        DB::transaction(function () use ($application): void {
            ForwardApplication::query()
                ->where('application_id', $application->id)
                ->update(['candidate_name_snapshot' => 'Candidat supprimé', 'application_id' => null]);

            $application->delete();
        });

        Storage::disk('private')->delete($paths);

        $this->audit->log(
            $actor,
            $purged ? AuditAction::ApplicationPurged : AuditAction::ApplicationDeleted,
            $application,
            ['files_deleted' => count($paths)],
            $ip,
        );
    }

    /** @return list<string> */
    private function paths(Application $application): array
    {
        $paths = [];

        if ($application->cv_path !== null && $application->cv_path !== '') {
            $paths[] = $application->cv_path;
        }

        foreach ($application->files ?? [] as $file) {
            $path = $file['path'] ?? null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
