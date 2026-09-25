<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterviewDecision;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property int $created_by
 * @property InterviewType $type
 * @property CarbonImmutable $starts_at
 * @property int $duration_minutes
 * @property string|null $location_or_link
 * @property list<string>|null $participants
 * @property InterviewStatus $status
 * @property string|null $notes
 * @property InterviewDecision|null $decision
 * @property CarbonImmutable|null $invitation_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'starts_at',
        'duration_minutes',
        'location_or_link',
        'participants',
        'status',
        'notes',
        'decision',
        'invitation_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InterviewType::class,
            'starts_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'participants' => 'array',
            'status' => InterviewStatus::class,
            'decision' => InterviewDecision::class,
            'invitation_sent_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
