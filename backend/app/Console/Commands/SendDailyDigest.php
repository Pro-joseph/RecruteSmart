<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DailyDigestMail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * EF-1102: one digest email per recruiter summarising the applications
 * received in the last 24 hours. Scheduled daily in routes/console.php.
 */
#[Signature('notifications:daily-digest
        {--dry-run : Show what would be sent without sending anything}')]
#[Description('Send the daily digest of new applications to each recruiter.')]
class SendDailyDigest extends Command
{
    public function handle(): int
    {
        $since = now()->subDay();

        $rows = DB::table('users')
            ->join('offers', 'offers.user_id', '=', 'users.id')
            ->join('applications', 'applications.offer_id', '=', 'offers.id')
            ->where('applications.created_at', '>=', $since)
            ->groupBy('users.id', 'users.email', 'offers.id', 'offers.title')
            ->selectRaw('users.id as user_id, users.email, offers.id as offer_id, offers.title, count(applications.id) as total')
            ->get();

        $byUser = $rows->groupBy('user_id');

        if ($byUser->isEmpty()) {
            $this->info('No new application in the last 24 hours.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($byUser as $rows) {
            $offers = $rows->map(fn ($row) => [
                'offer_id' => (int) $row->offer_id,
                'offer_title' => $row->title,
                'count' => (int) $row->total,
            ])->values()->all();

            $total = array_sum(array_column($offers, 'count'));

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s: %d candidature(s)', $rows->first()->email, $total));

                continue;
            }

            try {
                Mail::to($rows->first()->email)->send(new DailyDigestMail($offers, $total));
                $sent++;
            } catch (\Throwable $e) {
                $this->warn(sprintf('Digest failed for %s: %s', $rows->first()->email, $e->getMessage()));
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run done.' : sprintf('%d digest(s) sent.', $sent));

        return self::SUCCESS;
    }
}
