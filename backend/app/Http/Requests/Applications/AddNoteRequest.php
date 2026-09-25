<?php

declare(strict_types=1);

namespace App\Http\Requests\Applications;

use Illuminate\Foundation\Http\FormRequest;

class AddNoteRequest extends FormRequest
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
            'note' => ['required_without:rating', 'nullable', 'string', 'max:2000'],
            'rating' => ['required_without:note', 'nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
