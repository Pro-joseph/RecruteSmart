<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormFieldService
{
    /**
     * Locked fields: always present and required (EF-303).
     *
     * @var list<string>
     */
    public const LOCKED_KEYS = ['full_name', 'email', 'cv'];

    /**
     * @return array<string, array{label: string, type: string, locked: bool, sensitive: bool, required: bool}>
     */
    public function catalog(): array
    {
        /** @var array<string, array{label: string, type: string, locked: bool, sensitive: bool, required: bool}> $fields */
        $fields = config('recruitment.fields', []);

        return $fields;
    }

    /**
     * Seed the locked trio on offer creation.
     */
    public function seedLocked(Offer $offer): void
    {
        $catalog = $this->catalog();
        $position = 0;

        foreach (self::LOCKED_KEYS as $key) {
            if (! isset($catalog[$key])) {
                continue;
            }
            $offer->formFields()->firstOrCreate(
                ['key' => $key],
                [
                    'label' => $catalog[$key]['label'],
                    'type' => $catalog[$key]['type'],
                    'is_required' => true,
                    'is_locked' => true,
                    'is_sensitive' => false,
                    'position' => $position++,
                ]
            );
        }
    }

    /**
     * Replace the offer's form configuration.
     *
     * Server owns is_locked / is_sensitive (from catalog); client input for
     * those flags is rejected by SyncFormFieldsRequest. Locked fields must
     * stay present and required (EF-303).
     *
     * RG-12 (hide instead of delete once applications exist) lands in Lot 2
     * with the applications table; until then configuration is replaced.
     *
     * @param  list<array<string, mixed>>  $fields
     */
    public function sync(Offer $offer, array $fields): void
    {
        $catalog = $this->catalog();
        $keys = array_column($fields, 'key');

        foreach (self::LOCKED_KEYS as $locked) {
            $index = array_search($locked, $keys, true);
            if ($index === false) {
                throw ValidationException::withMessages([
                    'fields' => ["Locked field '{$locked}' must be present."],
                ]);
            }
            if (($fields[$index]['is_required'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'fields' => ["Locked field '{$locked}' must stay required."],
                ]);
            }
        }

        DB::transaction(function () use ($offer, $fields, $catalog): void {
            $offer->formFields()->delete();

            $position = 0;
            foreach ($fields as $field) {
                $key = $field['key'];
                $offer->formFields()->create([
                    'key' => $key,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'is_required' => in_array($key, self::LOCKED_KEYS, true) ? true : (bool) ($field['is_required'] ?? false),
                    'is_locked' => in_array($key, self::LOCKED_KEYS, true),
                    'is_sensitive' => (bool) ($catalog[$key]['sensitive'] ?? false),
                    'is_hidden' => (bool) ($field['is_hidden'] ?? false),
                    'options' => $field['options'] ?? null,
                    'rules' => $field['rules'] ?? null,
                    'position' => $position++,
                ]);
            }
        });
    }
}
