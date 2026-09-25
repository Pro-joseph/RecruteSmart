<?php

declare(strict_types=1);

namespace App\Http\Requests\Forwards;

use Illuminate\Foundation\Http\FormRequest;

class CreateForwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_emails' => ['required', 'array', 'max:10'],
            'to_emails.*' => ['email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'include_analysis' => ['sometimes', 'boolean'],
            'application_ids' => ['sometimes', 'array', 'max:50'],
            'application_ids.*' => ['integer'],
            'offer_id' => ['required_without:application_ids', 'integer', 'exists:offers,id'],
            'filters' => ['sometimes', 'array'],
            'select_all' => ['sometimes', 'boolean'],
        ];
    }
}
