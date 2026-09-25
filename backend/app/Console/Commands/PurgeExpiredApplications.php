<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\Applications\ApplicationDeleter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * EF-1202 / RG-13: deletes applications older than the retention period
 * (RETENTION_MONTHS, measured from the application's own submission date).
 * Scheduled daily in routes/console.php.
 */
#[Signature('applications:purge-expired
        {--dry-run : List the applications that would be purged without deleting them}')]
#[Description('Purge applications past the retention period (data and files).')]
class PurgeExpiredApplications extends Command
{
    public function handle(ApplicationDeleter $deleter): int
    {
        $months = (int) config('recruitment.retention_months', 12);
        $cutoff = now()->subMonths($months);

        $expired = Application::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get(['id', 'offer_id', 'cv_path', 'files', 'created_at']);

        if ($expired->isEmpty()) {
            $this->info('No expired application.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d application(s) older than %d months.', $expired->count(), $months));

        if ($this->option('dry-run')) {
            foreach ($expired as $application) {
                $this->line(sprintf('#%d (offer #%d, %s)', $application->id, $application->offer_id, $application->created_at));
            }

            return self::SUCCESS;
        }

        foreach ($expired as $application) {
            $deleter->delete($application, null, null, true);
        }

        $this->info('Purged.');

        return self::SUCCESS;
    }
}
