<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\AtsVerdict;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property AnalysisStatus $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $criteria_version
 * @property string $prompt_version
 * @property string|null $input_hash
 * @property string|null $llm_provider
 * @property string|null $llm_model
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property int|null $ats_score
 * @property AtsVerdict|null $ats_verdict
 * @property list<array<string, mixed>>|null $ats_checks
 * @property int|null $match_score
 * @property array<string, mixed>|null $match_breakdown
 * @property array<string, bool>|null $knockout_flags
 * @property string|null $summary
 * @property list<string>|null $strengths
 * @property list<string>|null $gaps
 * @property list<string>|null $anomalies
 * @property string|null $years_experience
 * @property string|null $city
 * @property string|null $country
 * @property string|null $city_normalized
 * @property list<array<string, mixed>>|null $languages
 * @property list<array<string, mixed>>|null $education
 * @property CarbonImmutable|null $analyzed_at
 */
class ApplicationAnalysis extends Model
{
    /** @use HasFactory<ApplicationAnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'status',
        'error_code',
        'error_message',
        'criteria_version',
        'prompt_version',
        'input_hash',
        'llm_provider',
        'llm_model',
        'tokens_in',
        'tokens_out',
        'ats_score',
        'ats_verdict',
        'ats_checks',
        'match_score',
        'match_breakdown',
        'knockout_flags',
        'summary',
        'strengths',
        'gaps',
        'anomalies',
        'years_experience',
        'city',
        'country',
        'city_normalized',
        'languages',
        'education',
        'analyzed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'criteria_version' => 1,
        'prompt_version' => 'v1',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'ats_verdict' => AtsVerdict::class,
            'ats_checks' => 'array',
            'match_breakdown' => 'array',
            'knockout_flags' => 'array',
            'strengths' => 'array',
            'gaps' => 'array',
            'anomalies' => 'array',
            'languages' => 'array',
            'education' => 'array',
            'years_experience' => 'decimal:1',
            'analyzed_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
