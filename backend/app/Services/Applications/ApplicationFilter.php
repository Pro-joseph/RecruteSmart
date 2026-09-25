<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\AtsVerdict;
use App\Models\Application;
use App\Services\Analysis\SkillNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Applies the spec §5.3 filter/sort/search parameters (EF-507, EF-801→807)
 * to the applications list query. All filters combine with AND logic.
 */
class ApplicationFilter
{
    /**
     * Spec §5.3 sort whitelist, mapped to their physical columns.
     *
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'match_score' => 'application_analyses.match_score',
        'ats_score' => 'application_analyses.ats_score',
        'years_experience' => 'application_analyses.years_experience',
        'created_at' => 'applications.created_at',
        'full_name' => 'applications.full_name',
    ];

    /**
     * Filters that read from the application_analyses table.
     *
     * @var list<string>
     */
    private const ANALYSIS_KEYS = ['min_score', 'max_score', 'ats', 'ats_min', 'ats_max', 'exp_min', 'exp_max', 'city'];

    public function __construct(private readonly SkillNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $filters  Validated query parameters.
     * @return Builder<Application>
     */
    public function apply(Builder $applications, array $filters): Builder
    {
        $sort = (string) ($filters['sort'] ?? '-match_score');
        $sortKey = ltrim($sort, '-');
        if (! isset(self::SORT_COLUMNS[$sortKey])) {
            $sort = '-match_score';
            $sortKey = 'match_score';
        }
        $sortColumn = self::SORT_COLUMNS[$sortKey];

        $hasAnalysisFilter = false;
        foreach (self::ANALYSIS_KEYS as $key) {
            if (array_key_exists($key, $filters)) {
                $hasAnalysisFilter = true;
                break;
            }
        }

        if ($hasAnalysisFilter || $sortColumn !== 'applications.created_at' && $sortColumn !== 'applications.full_name') {
            $applications->leftJoin('application_analyses', 'application_analyses.application_id', '=', 'applications.id')
                ->select('applications.*');
        }

        if (isset($filters['min_score'])) {
            $applications->where('application_analyses.match_score', '>=', (int) $filters['min_score']);
        }
        if (isset($filters['max_score'])) {
            $applications->where('application_analyses.match_score', '<=', (int) $filters['max_score']);
        }

        $this->applyAts($applications, $filters);

        if (isset($filters['exp_min'])) {
            $applications->where('application_analyses.years_experience', '>=', (float) $filters['exp_min']);
        }
        if (isset($filters['exp_max'])) {
            $applications->where('application_analyses.years_experience', '<=', (float) $filters['exp_max']);
        }

        if (isset($filters['city'])) {
            $applications->where(
                'application_analyses.city_normalized',
                (string) $this->normalizer->normalizeCity((string) $filters['city']),
            );
        }
        if (isset($filters['country'])) {
            $applications->whereRaw(
                'lower(application_analyses.country) = ?',
                [mb_strtolower((string) $filters['country'])],
            );
        }

        $this->applySkills($applications, $filters);

        if (isset($filters['status']) && is_array($filters['status']) && $filters['status'] !== []) {
            $applications->whereIn('applications.status', array_map(strval(...), $filters['status']));
        }

        if (isset($filters['applied_from'])) {
            $applications->where('applications.created_at', '>=', Carbon::parse((string) $filters['applied_from'])->startOfDay());
        }
        if (isset($filters['applied_to'])) {
            $applications->where('applications.created_at', '<=', Carbon::parse((string) $filters['applied_to'])->endOfDay());
        }

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $this->applySearch($applications, (string) $filters['q']);
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $applications->orderBy($sortColumn, $direction)->orderBy('applications.id', 'desc');
    }

    /**
     * Spec §5.3: `ats` matches the verdict, `ats_min`/`ats_max` the score
     * range; when both are given they combine with OR inside the ATS family.
     *
     * @param  array<string, mixed>  $filters
     * @param  Builder<Application>  $applications
     */
    private function applyAts(Builder $applications, array $filters): void
    {
        $verdict = isset($filters['ats']) ? AtsVerdict::from((string) $filters['ats'])->value : null;
        $hasRange = isset($filters['ats_min']) || isset($filters['ats_max']);

        if ($verdict !== null && $hasRange) {
            $applications->where(fn ($query) => $query
                ->where('application_analyses.ats_verdict', $verdict)
                ->orWhere(fn ($range) => $this->atsRange($range, $filters)));

            return;
        }

        if ($verdict !== null) {
            $applications->where('application_analyses.ats_verdict', $verdict);
        } elseif ($hasRange) {
            $this->atsRange($applications, $filters);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<Application>  $query
     */
    private function atsRange(Builder $query, array $filters): void
    {
        if (isset($filters['ats_min'])) {
            $query->where('application_analyses.ats_score', '>=', (int) $filters['ats_min']);
        }
        if (isset($filters['ats_max'])) {
            $query->where('application_analyses.ats_score', '<=', (int) $filters['ats_max']);
        }
    }

    /**
     * Spec §5.3: `any` = at least one selected skill, `all` = every one.
     *
     * @param  array<string, mixed>  $filters
     * @param  Builder<Application>  $applications
     */
    private function applySkills(Builder $applications, array $filters): void
    {
        $skills = $filters['skills'] ?? [];
        if (! is_array($skills) || $skills === []) {
            return;
        }

        if (($filters['skills_mode'] ?? 'any') === 'all') {
            foreach ($skills as $slug) {
                $applications->whereHas('skills', fn ($query) => $query->where('slug', (string) $slug));
            }

            return;
        }

        $applications->whereHas('skills', fn ($query) => $query->whereIn('slug', array_map(strval(...), $skills)));
    }

    /**
     * EF-507: case-insensitive match on name, email or skill.
     *
     * @param  Builder<Application>  $applications
     */
    private function applySearch(Builder $applications, string $term): void
    {
        $like = '%'.mb_strtolower($term).'%';

        $applications->where(fn ($query) => $query
            ->whereRaw('lower(applications.full_name) like ?', [$like])
            ->orWhereRaw('lower(applications.email) like ?', [$like])
            ->orWhereHas('skills', fn ($skill) => $skill->where(fn ($match) => $match
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(slug) like ?', [$like])
            )));
    }
}
