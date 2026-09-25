<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Enums\ApplicationStatus;
use App\Enums\AtsVerdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return is_object($offer)
            && $this->user()?->can('view', $offer) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'ats' => ['sometimes', 'string', Rule::enum(AtsVerdict::class)],
            'ats_min' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'ats_max' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['string', 'max:100'],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'exp_min' => ['sometimes', 'numeric', 'min:0', 'max:60'],
            'exp_max' => ['sometimes', 'numeric', 'min:0', 'max:60'],
            'city' => ['sometimes', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['string', Rule::enum(ApplicationStatus::class)],
            'applied_from' => ['sometimes', 'date'],
            'applied_to' => ['sometimes', 'date'],
            'q' => ['sometimes', 'string', 'max:200'],
            'sort' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
