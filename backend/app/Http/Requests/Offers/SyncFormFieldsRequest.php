<?php

declare(strict_types=1);

namespace App\Http\Requests\Offers;

use App\Enums\FieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncFormFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return is_object($offer)
            && $this->user()?->can('manageFormFields', $offer) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::enum(FieldType::class)],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.is_hidden' => ['sometimes', 'boolean'],
            'fields.*.is_locked' => ['prohibited'],
            'fields.*.is_sensitive' => ['prohibited'],
            'fields.*.options' => ['nullable', 'array', Rule::requiredIf(fn (): bool => in_array($this->input('fields.*.type'), ['select', 'multiselect'], true))],
            'fields.*.options.*' => ['string', 'max:255'],
            'fields.*.rules' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.regex' => 'Field keys must be snake_case (a-z, 0-9, underscore).',
            'fields.*.is_locked.prohibited' => 'Locked and sensitive flags are managed by the server.',
            'fields.*.is_sensitive.prohibited' => 'Locked and sensitive flags are managed by the server.',
        ];
    }
}
