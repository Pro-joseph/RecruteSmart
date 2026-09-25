<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Enums\ApplicationStatus;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * EF-508: per-offer dashboard numbers — application totals, status split,
 * average match/ATS scores and score distribution buckets.
 */
class OfferStatsService
{
    /** @return array<string, mixed> */
    public function for(Offer $offer): array
    {
        $byStatus = array_fill_keys(array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::cases()), 0);

        $statusRows = DB::table('applications')
            ->where('offer_id', $offer->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        foreach ($statusRows as $row) {
            $byStatus[$row->status] = (int) $row->total;
        }

        $scoreRow = DB::table('applications')
            ->join('application_analyses', 'application_analyses.application_id', '=', 'applications.id')
            ->where('applications.offer_id', $offer->id)
            ->where('application_analyses.status', 'completed')
            ->selectRaw(
                'avg(application_analyses.match_score) as avg_match,
                 avg(application_analyses.ats_score) as avg_ats,
                 sum(case when application_analyses.match_score between 0 and 39 then 1 else 0 end) as bucket_0_39,
                 sum(case when application_analyses.match_score between 40 and 59 then 1 else 0 end) as bucket_40_59,
                 sum(case when application_analyses.match_score between 60 and 79 then 1 else 0 end) as bucket_60_79,
                 sum(case when application_analyses.match_score between 80 and 100 then 1 else 0 end) as bucket_80_100'
            )
            ->first();

        $unscored = DB::table('applications')
            ->leftJoin('application_analyses', 'application_analyses.application_id', '=', 'applications.id')
            ->where('applications.offer_id', $offer->id)
            ->where(fn ($query) => $query
                ->whereNull('application_analyses.id')
                ->orWhere('application_analyses.status', '!=', 'completed')
                ->orWhereNull('application_analyses.match_score'))
            ->count();

        return [
            'applications_count' => (int) DB::table('applications')->where('offer_id', $offer->id)->count(),
            'by_status' => $byStatus,
            'avg_match_score' => $scoreRow->avg_match === null ? null : (int) round((float) $scoreRow->avg_match),
            'avg_ats_score' => $scoreRow->avg_ats === null ? null : (int) round((float) $scoreRow->avg_ats),
            'score_buckets' => [
                '0_39' => (int) $scoreRow->bucket_0_39,
                '40_59' => (int) $scoreRow->bucket_40_59,
                '60_79' => (int) $scoreRow->bucket_60_79,
                '80_100' => (int) $scoreRow->bucket_80_100,
                'unscored' => (int) $unscored,
            ],
        ];
    }
}
