<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $offer_id
 * @property ApplicationStatus $status
 * @property array<string, mixed>|null $answers
 * @property list<array<string, mixed>>|null $files
 * @property CarbonImmutable|null $consent_at
 * @property CarbonImmutable|null $created_at
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'status',
        'rating',
        'answers',
        'cv_path',
        'cv_original_name',
        'cv_mime',
        'cv_size',
        'files',
        'consent_at',
        'consent_version',
    ];

    protected $attributes = [
        'answers' => '[]',
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'answers' => 'array',
            'files' => 'array',
            'consent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /** @return HasOne<ApplicationAnalysis, $this> */
    public function analysis(): HasOne
    {
        return $this->hasOne(ApplicationAnalysis::class);
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /** @param Builder<$this> $query */
    public function scopeForRecruiter($query, int $userId): void
    {
        $query->whereHas('offer', fn ($q) => $q->where('user_id', $userId));
    }
}
