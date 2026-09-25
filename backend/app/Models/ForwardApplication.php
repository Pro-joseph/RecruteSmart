<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $forward_id
 * @property int|null $application_id
 * @property string $candidate_name_snapshot
 */
class ForwardApplication extends Model
{
    public const UPDATED_AT = null;

    /** @var string */
    protected $table = 'forward_application';

    protected $fillable = [
        'application_id',
        'candidate_name_snapshot',
    ];

    /** @return BelongsTo<Forward, $this> */
    public function forward(): BelongsTo
    {
        return $this->belongsTo(Forward::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
