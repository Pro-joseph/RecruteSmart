<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldType;
use Database\Factories\OfferFormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FieldType $type
 * @property bool $is_required
 * @property bool $is_locked
 * @property bool $is_sensitive
 * @property bool $is_hidden
 * @property list<string>|null $options
 * @property array<string, mixed>|null $rules
 */
class OfferFormField extends Model
{
    /** @use HasFactory<OfferFormFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'type',
        'is_required',
        'is_locked',
        'is_sensitive',
        'is_hidden',
        'options',
        'rules',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'is_required' => 'boolean',
            'is_locked' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_hidden' => 'boolean',
            'options' => 'array',
            'rules' => 'array',
        ];
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
