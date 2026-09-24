<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OfferFormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OfferFormField
 */
class OfferFormFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OfferFormField $field */
        $field = $this->resource;

        return [
            'id' => $field->id,
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'is_required' => $field->is_required,
            'is_locked' => $field->is_locked,
            'is_sensitive' => $field->is_sensitive,
            'is_hidden' => $field->is_hidden,
            'options' => $field->options,
            'rules' => $field->rules,
            'position' => $field->position,
        ];
    }
}
