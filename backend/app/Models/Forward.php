<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ForwardDelivery;
use App\Enums\ForwardStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ForwardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property list<string> $to_emails
 * @property string $subject
 * @property string|null $message
 * @property ForwardDelivery $delivery
 * @property bool $include_analysis
 * @property ForwardStatus $status
 * @property string|null $error_message
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Forward extends Model
{
    /** @use HasFactory<ForwardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'to_emails',
        'subject',
        'message',
        'delivery',
        'include_analysis',
        'status',
        'error_message',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'include_analysis' => 'boolean',
            'delivery' => ForwardDelivery::class,
            'status' => ForwardStatus::class,
            'sent_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Application, $this> */
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'forward_application')
            ->withPivot('candidate_name_snapshot');
    }

    /** @return HasMany<ForwardApplication, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ForwardApplication::class);
    }
}
