<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property ApplicationStatus $status
 * @property array<string, mixed>|null $answers
 * @property list<array<string, mixed>>|null $files
 * @property \Carbon\CarbonImmutable|null $consent_at
 * @property \Carbon\CarbonImmutable|null $created_at
 */
class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
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

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopeForRecruiter($query, int $userId): void
    {
        $query->whereHas('offer', fn ($q) => $q->where('user_id', $userId));
    }
}
