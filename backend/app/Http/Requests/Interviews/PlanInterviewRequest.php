<?php

declare(strict_types=1);

namespace App\Http\Requests\Interviews;

use App\Enums\InterviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (is_object($application)) {
            $application->loadMissing('offer');
        }

        return is_object($application)
            && $this->user()?->can('update', $application) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(InterviewType::class)],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'location_or_link' => ['nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'array', 'max:10'],
            'participants.*' => ['email', 'max:255'],
        ];
    }
}
