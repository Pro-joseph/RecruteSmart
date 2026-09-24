<?php

declare(strict_types=1);

namespace App\Http\Requests\Offers;

class UpdateOfferRequest extends StoreOfferRequest
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
        $rules = parent::rules();

        foreach ($rules as $field => $rule) {
            $rules[$field] = array_merge(['sometimes'], (array) $rule);
        }

        // Status, token and version change only via dedicated endpoints.
        $rules['status'] = ['prohibited'];
        $rules['public_token'] = ['prohibited'];
        $rules['criteria_version'] = ['prohibited'];

        return $rules;
    }
}
