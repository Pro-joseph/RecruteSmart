<?php

declare(strict_types=1);

namespace App\Http\Requests\Offers;

use App\Enums\OfferType;
use App\Enums\WorkMode;
use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Offer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(OfferType::class)],
            'type_label' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn (): bool => $this->string('type')->toString() === OfferType::Other->value)],
            'description' => ['required', 'string'],
            'missions' => ['nullable', 'string'],
            'profile_wanted' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['nullable', 'string', Rule::enum(WorkMode::class)],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'positions_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:64'],
            'preferred_skills' => ['nullable', 'array'],
            'preferred_skills.*' => ['string', 'max:64'],
            'min_experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'array'],
            'languages.*.name' => ['required_with:languages', 'string', 'max:64'],
            'languages.*.level' => ['nullable', 'string', 'max:64'],
            'knockout_criteria' => ['nullable', 'array'],
            'knockout_criteria.*' => ['string', Rule::in(['required_skills', 'preferred_skills', 'experience', 'education', 'languages'])],
            'scoring_weights' => ['nullable', 'array'],
            'scoring_weights.required_skills' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scoring_weights.preferred_skills' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scoring_weights.experience' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scoring_weights.education' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scoring_weights.languages' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
