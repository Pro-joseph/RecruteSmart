<?php

declare(strict_types=1);

namespace App\Http\Requests\Interviews;

use App\Enums\InterviewDecision;
use App\Enums\InterviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interview = $this->route('interview');

        if (is_object($interview)) {
            $interview->loadMissing('application.offer');
        }

        return is_object($interview)
            && $this->user()?->can('update', $interview->application) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(InterviewStatus::class)],
            'starts_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'location_or_link' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'decision' => ['sometimes', 'nullable', Rule::enum(InterviewDecision::class)],
        ];
    }
}
