<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferFormField extends Model
{
    /** @use HasFactory<\Database\Factories\OfferFormFieldFactory> */
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
