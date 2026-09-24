<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\FieldType;
use App\Models\OfferFormField;

/**
 * Maps a configured form field to Laravel validation rules (§5.4).
 *
 * File/image fields are submitted under `files.{key}`, everything else
 * under `answers.{key}`.
 */
class FieldRuleMapper
{
    public function isFileField(OfferFormField $field): bool
    {
        return in_array($field->type, [FieldType::File, FieldType::Image], true);
    }

    /**
     * @return list<string>
     */
    public function rulesFor(OfferFormField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        match ($field->type) {
            FieldType::Text => array_push($rules, 'string', 'max:255'),
            FieldType::Textarea => array_push($rules, 'string', 'max:10000'),
            FieldType::Number => array_push($rules, 'numeric'),
            FieldType::Email => array_push($rules, 'email', 'max:255'),
            FieldType::Phone => array_push($rules, 'string', 'max:32'),
            FieldType::Url => array_push($rules, 'url', 'max:2048'),
            FieldType::Date => array_push($rules, 'date'),
            FieldType::Select => array_push($rules, 'string', 'in:'.implode(',', $field->options ?? [])),
            FieldType::Multiselect => array_push($rules, 'array'),
            FieldType::Boolean => array_push($rules, 'boolean'),
            FieldType::File => array_push($rules, 'file', 'mimes:pdf,docx', 'max:5120'),
            FieldType::Image => array_push($rules, 'image', 'mimes:jpg,jpeg,png', 'max:2048'),
        };

        if ($field->type === FieldType::Multiselect) {
            // Options are validated per item via `{key}.*` in the request.
        }

        return $rules;
    }
}
