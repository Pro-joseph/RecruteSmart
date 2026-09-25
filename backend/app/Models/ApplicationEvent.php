<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationEventType;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property int|null $user_id
 * @property ApplicationEventType $type
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $created_at
 */
class ApplicationEvent extends Model
{
    /** @use HasFactory<ApplicationEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationEventType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
