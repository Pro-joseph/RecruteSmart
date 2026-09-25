<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return is_object($offer)
            && $this->user()?->can('update', $offer) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_ids' => ['required', 'array', 'min:1', 'max:200'],
            'application_ids.*' => ['integer'],
            'status' => ['required', 'string', Rule::enum(ApplicationStatus::class)],
        ];
    }
}
