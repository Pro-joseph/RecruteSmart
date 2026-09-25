<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\Application;
use App\Models\ApplicationAnalysis;
use App\Models\Offer;
use App\Models\Skill;
use App\Services\Analysis\Ats\AtsContext;
use App\Services\Analysis\Ats\AtsScorer;
use App\Services\Analysis\CvAnalyzer;
use App\Services\Analysis\CvTextExtractor;
use App\Services\Analysis\CvTextSanitizer;
use App\Services\Analysis\InvalidAnalysisOutputException;
use App\Services\Analysis\PromptInjectionDetector;
use App\Services\Analysis\ProviderException;
use App\Services\Analysis\ScoreCalculator;
use App\Services\Analysis\SkillNormalizer;
use App\Services\Analysis\UnreadableCvException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnalyzeApplicationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $timeout = 180;

    public function __construct(public readonly int $applicationId)
    {
        $this->queue = 'analysis';
    }

    public function uniqueId(): string
    {
        return 'analysis-'.$this->applicationId;
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('llm'),
            (new WithoutOverlapping('analysis-'.$this->applicationId))->releaseAfter(60),
        ];
    }

    public function handle(
        CvTextExtractor $extractor,
        AtsScorer $scorer,
        CvTextSanitizer $sanitizer,
        PromptInjectionDetector $injectionDetector,
        ScoreCalculator $calculator,
        SkillNormalizer $normalizer,
        CvAnalyzer $analyzer,
    ): void {
        $application = Application::with('offer')->find($this->applicationId);

        if ($application === null || $application->offer === null) {
            return;
        }

        $offer = $application->offer;
        $analysis = ApplicationAnalysis::firstOrCreate(
            ['application_id' => $application->id],
            [
                'status' => AnalysisStatus::Pending,
                'criteria_version' => $offer->criteria_version,
                'prompt_version' => (string) config('llm.prompt_version', 'v1'),
            ],
        );
        $previousCompleted = $analysis->status === AnalysisStatus::Completed;
        $previousHash = $analysis->input_hash;

        if (! $this->quotaAvailable((int) $offer->user_id)) {
            $this->markFailed($analysis, 'quota_exceeded', 'Quota journalier d’analyses IA atteint pour ce recruteur.');

            return;
        }

        $analysis->fill([
            'status' => AnalysisStatus::Processing,
            'criteria_version' => $offer->criteria_version,
            'prompt_version' => (string) config('llm.prompt_version', 'v1'),
        ])->save();

        try {
            $extracted = $extractor->extract(
                Storage::disk('private')->path($application->cv_path),
                $application->cv_mime,
            );
        } catch (UnreadableCvException $e) {
            $this->markFailed($analysis, 'unreadable_cv', $e->getMessage());

            return;
        }

        // ATS is always computed from the raw text (spec §6.2 step 4).
        $ats = $scorer->score(new AtsContext(
            text: $extracted->text,
            mime: $extracted->mime,
            pages: $extracted->pages,
            requiredSkills: $offer->required_skills ?? [],
        ));

        // Insufficient text: complete without any LLM call (spec §6.2 step 3).
        if (mb_strlen($extracted->text) < 200) {
            $analysis->fill([
                'status' => AnalysisStatus::Completed,
                'ats_score' => $ats['score'],
                'ats_verdict' => $ats['verdict'],
                'ats_checks' => $ats['checks'],
                'match_score' => null,
                'match_breakdown' => null,
                'knockout_flags' => null,
                'anomalies' => ['texte_non_extractible'],
                'error_code' => null,
                'error_message' => null,
                'analyzed_at' => now(),
            ])->save();

            return;
        }

        $sanitized = $sanitizer->sanitize($extracted->text, $application->full_name);
        $offerContext = $this->offerContext($offer);
        $hash = hash('sha256', (string) json_encode(
            [$analysis->prompt_version, $offer->criteria_version, $offerContext, $sanitized],
            JSON_UNESCAPED_UNICODE,
        ));

        // Idempotence: identical inputs reuse the previous completed result (§6.7).
        if ($previousCompleted && $previousHash === $hash) {
            $analysis->fill(['status' => AnalysisStatus::Completed])->save();

            return;
        }

        try {
            $result = $analyzer->analyze($sanitized, $offerContext);
        } catch (InvalidAnalysisOutputException $e) {
            $this->markFailed($analysis, 'invalid_output', $e->getMessage());

            return;
        } catch (ProviderException $e) {
            if ($e->errorCode === 'quota_exceeded') {
                $this->markFailed($analysis, 'quota_exceeded', $e->getMessage());

                return;
            }

            // Transient provider failures rely on the job retry/backoff schedule.
            throw $e;
        }

        $payload = $result->payload;
        $calculated = $calculator->calculate($offer, $payload);

        $injections = $injectionDetector->detect($sanitized);
        $anomalies = array_values(array_unique(array_merge(
            is_array($payload['anomalies'] ?? null) ? $payload['anomalies'] : [],
            $this->coherenceAnomalies($payload),
            $injections,
        )));

        $formCity = $application->answers['city'] ?? null;
        $city = is_string($formCity) && trim($formCity) !== ''
            ? trim($formCity)
            : ($payload['location']['city'] ?? null);

        $skillIds = $this->syncSkills($application, is_array($payload['skills'] ?? null) ? $payload['skills'] : [], $normalizer);
        $cityNormalized = $normalizer->normalizeCity(is_string($city) ? $city : null);

        DB::transaction(function () use (
            $analysis, $payload, $calculated, $ats, $result, $anomalies, $city, $cityNormalized, $skillIds, $application
        ): void {
            $analysis->fill([
                'status' => AnalysisStatus::Completed,
                'error_code' => null,
                'error_message' => null,
                'llm_provider' => $result->provider,
                'llm_model' => $result->model,
                'tokens_in' => $result->tokensIn,
                'tokens_out' => $result->tokensOut,
                'ats_score' => $ats['score'],
                'ats_verdict' => $ats['verdict'],
                'ats_checks' => $ats['checks'],
                'match_score' => $calculated['match_score'],
                'match_breakdown' => $calculated['match_breakdown'],
                'knockout_flags' => $calculated['knockout_flags'],
                'summary' => is_string($payload['summary'] ?? null) ? $payload['summary'] : null,
                'strengths' => is_array($payload['strengths'] ?? null) ? $payload['strengths'] : [],
                'gaps' => is_array($payload['gaps'] ?? null) ? $payload['gaps'] : [],
                'anomalies' => $anomalies,
                'years_experience' => is_numeric($payload['years_experience_total'] ?? null)
                    ? min(60, max(0, (float) $payload['years_experience_total']))
                    : null,
                'city' => is_string($city) ? $city : null,
                'country' => is_string($payload['location']['country'] ?? null) ? $payload['location']['country'] : null,
                'city_normalized' => $cityNormalized,
                'languages' => is_array($payload['languages'] ?? null) ? $payload['languages'] : [],
                'education' => is_array($payload['education'] ?? null) ? $payload['education'] : [],
                'analyzed_at' => now(),
            ])->save();

            $application->skills()->sync($skillIds);
        });

        // input_hash is stored after the payload (computed from prompt/criteria/text).
        $analysis->forceFill(['input_hash' => $hash])->save();
    }

    public function failed(?\Throwable $exception): void
    {
        $analysis = ApplicationAnalysis::where('application_id', $this->applicationId)->first();
        if ($analysis === null) {
            return;
        }

        $code = 'provider_error';
        $message = $exception?->getMessage() ?? 'Erreur inconnue pendant l’analyse IA.';

        if ($exception instanceof ProviderException) {
            $code = $exception->errorCode;
        } elseif ($exception instanceof InvalidAnalysisOutputException) {
            $code = 'invalid_output';
        } elseif ($exception instanceof UnreadableCvException) {
            $code = 'unreadable_cv';
        }

        $this->markFailed($analysis, $code, $message);
    }

    private function quotaAvailable(int $userId): bool
    {
        $limit = (int) config('llm.daily_limit_per_user', 300);

        if ($limit <= 0) {
            return true;
        }

        $doneToday = ApplicationAnalysis::where('status', AnalysisStatus::Completed)
            ->where('analyzed_at', '>=', now()->startOfDay())
            ->whereHas('application.offer', fn ($q) => $q->where('user_id', $userId))
            ->count();

        return $doneToday < $limit;
    }

    private function markFailed(ApplicationAnalysis $analysis, string $code, string $message): void
    {
        $analysis->fill([
            'status' => AnalysisStatus::Failed,
            'error_code' => $code,
            'error_message' => mb_substr($message, 0, 250),
            'analyzed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function offerContext(Offer $offer): array
    {
        return [
            'title' => $offer->title,
            'description' => mb_substr((string) $offer->description, 0, 1000),
            'required_skills' => $offer->required_skills ?? [],
            'preferred_skills' => $offer->preferred_skills ?? [],
            'min_experience_years' => $offer->min_experience_years,
            'education_level' => $offer->education_level,
            'languages' => $offer->languages ?? [],
        ];
    }

    /**
     * Spec §6.5: sub-score > 90 with an empty matched list is anomalous.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function coherenceAnomalies(array $payload): array
    {
        $anomalies = [];
        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

        foreach (['required_skills', 'preferred_skills'] as $key) {
            $score = $criteria[$key]['score'] ?? null;
            $matched = $criteria[$key]['matched'] ?? null;

            if (is_numeric($score) && $score > 90 && is_array($matched) && $matched === []) {
                $anomalies[] = "cohérence_{$key}: score {$score} sans compétence appariée";
            }
        }

        return $anomalies;
    }

    /**
     * @param  list<string>  $skills
     * @return list<int>
     */
    private function syncSkills(Application $application, array $skills, SkillNormalizer $normalizer): array
    {
        $ids = [];

        foreach ($normalizer->normalizeSkills($skills) as $skill) {
            $model = Skill::firstOrCreate(['slug' => $skill['slug']], ['name' => $skill['name']]);
            $ids[] = $model->id;
        }

        return $ids;
    }
}
