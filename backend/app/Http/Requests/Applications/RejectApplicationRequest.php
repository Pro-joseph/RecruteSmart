<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class RejectApplicationRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
            'send_email' => ['sometimes', 'boolean'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
