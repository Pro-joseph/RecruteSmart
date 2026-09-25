<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(ApplicationStatus::class)],
        ];
    }
}
