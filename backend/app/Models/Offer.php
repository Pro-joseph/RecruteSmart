<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    /** @use HasFactory<\Database\Factories\OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'type_label',
        'description',
        'missions',
        'profile_wanted',
        'city',
        'country',
        'work_mode',
        'salary_min',
        'salary_max',
        'salary_currency',
        'positions_count',
        'required_skills',
        'preferred_skills',
        'min_experience_years',
        'education_level',
        'languages',
        'knockout_criteria',
        'scoring_weights',
    ];

    protected $attributes = [
        'required_skills' => '[]',
        'preferred_skills' => '[]',
        'languages' => '[]',
        'knockout_criteria' => '[]',
        'positions_count' => 1,
        'criteria_version' => 1,
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'work_mode' => WorkMode::class,
            'status' => OfferStatus::class,
            'required_skills' => 'array',
            'preferred_skills' => 'array',
            'languages' => 'array',
            'knockout_criteria' => 'array',
            'scoring_weights' => 'array',
            'deadline_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OfferFormField, $this> */
    public function formFields(): HasMany
    {
        return $this->hasMany(OfferFormField::class)->orderBy('position');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopeForUser($query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<$this> $query */
    public function scopePublished($query): void
    {
        $query->where('status', OfferStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === OfferStatus::Published;
    }
}
