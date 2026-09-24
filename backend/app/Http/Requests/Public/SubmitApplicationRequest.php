<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Models\Offer;
use App\Services\Applications\FieldRuleMapper;
use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mapper = app(FieldRuleMapper::class);
        $offer = Offer::where('public_token', (string) $this->route('token'))
            ->with('formFields')
            ->first();

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'answers' => ['nullable', 'array'],
            'files.cv' => ['required', 'file', 'mimes:pdf,docx', 'max:5120'],
            'consent' => ['required', 'accepted'],
            'captcha_token' => ['nullable', 'string'],
            'website' => ['nullable', 'string'],
        ];

        if (! $offer instanceof Offer) {
            return $rules;
        }

        foreach ($offer->formFields as $field) {
            if ($field->is_hidden) {
                continue;
            }
            if (in_array($field->key, ['full_name', 'email', 'phone', 'cv'], true)) {
                if ($field->key === 'phone' && $field->is_required) {
                    $rules['phone'] = ['required', 'string', 'max:32'];
                }

                continue;
            }

            $prefix = $mapper->isFileField($field) ? 'files.' : 'answers.';
            $rules[$prefix.$field->key] = $mapper->rulesFor($field);

            if ($field->type->value === 'multiselect') {
                $options = implode(',', $field->options ?? []);
                $rules['answers.'.$field->key.'.*'] = ['string', 'in:'.$options];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L’adresse email est obligatoire.',
            'email.email' => 'L’adresse email n’est pas valide.',
            'files.cv.required' => 'Le CV est obligatoire.',
            'files.cv.mimes' => 'Le CV doit être un fichier PDF ou DOCX.',
            'files.cv.max' => 'Le CV ne doit pas dépasser 5 Mo.',
            'consent.accepted' => 'Vous devez accepter le traitement de vos données pour postuler.',
        ];
    }
}
