<?php

declare(strict_types=1);

namespace App\Services\Offers;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OfferService
{
    /**
     * Criteria keys whose change marks existing analyses stale (RG-09).
     *
     * @var list<string>
     */
    public const CRITERIA_KEYS = [
        'required_skills',
        'preferred_skills',
        'min_experience_years',
        'education_level',
        'languages',
        'knockout_criteria',
        'scoring_weights',
    ];

    public function __construct(private readonly FormFieldService $fields) {}

    /**
     * @param  array<string, mixed>  $data  Validated data.
     */
    public function create(User $user, array $data): Offer
    {
        return DB::transaction(function () use ($user, $data): Offer {
            /** @var Offer $offer */
            $offer = $user->offers()->create($data);
            $this->fields->seedLocked($offer);

            return $offer->fresh() ?? $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated data.
     */
    public function update(Offer $offer, array $data): Offer
    {
        $criteriaChanged = false;
        foreach (self::CRITERIA_KEYS as $key) {
            if (array_key_exists($key, $data) && $this->normalized($offer->getAttribute($key)) !== $this->normalized($data[$key] ?? null)) {
                $criteriaChanged = true;
                break;
            }
        }

        $offer->fill($data);
        if ($criteriaChanged) {
            $offer->criteria_version = $offer->criteria_version + 1;
        }
        $offer->save();

        return $offer->fresh() ?? $offer;
    }

    public function publish(Offer $offer): Offer
    {
        if ($offer->isPublished()) {
            return $offer;
        }

        $offer->forceFill([
            'status' => OfferStatus::Published,
            'published_at' => now(),
            'closed_at' => null,
            'public_token' => $offer->public_token ?? $this->uniqueToken(),
        ])->save();

        return $offer->fresh() ?? $offer;
    }

    public function close(Offer $offer): Offer
    {
        $offer->forceFill([
            'status' => OfferStatus::Closed,
            'closed_at' => now(),
        ])->save();

        return $offer->fresh() ?? $offer;
    }

    public function duplicate(User $user, Offer $offer): Offer
    {
        return DB::transaction(function () use ($user, $offer): Offer {
            $copy = $user->offers()->create([
                ...$offer->only([
                    'title', 'type', 'type_label', 'description', 'missions', 'profile_wanted',
                    'city', 'country', 'work_mode', 'salary_min', 'salary_max', 'salary_currency',
                    'positions_count', 'required_skills', 'preferred_skills', 'min_experience_years',
                    'education_level', 'languages', 'knockout_criteria', 'scoring_weights',
                ]),
                'title' => $offer->title.' (copie)',
                'status' => OfferStatus::Draft,
                'public_token' => null,
                'criteria_version' => 1,
                'deadline_at' => null,
                'published_at' => null,
                'closed_at' => null,
            ]);

            $offer->loadMissing('formFields');
            foreach ($offer->formFields as $field) {
                $copy->formFields()->create($field->only([
                    'key', 'label', 'type', 'is_required', 'is_locked',
                    'is_sensitive', 'is_hidden', 'options', 'rules', 'position',
                ]));
            }

            return $copy->fresh() ?? $copy;
        });
    }

    public function regenerateLink(Offer $offer): Offer
    {
        $offer->forceFill(['public_token' => $this->uniqueToken()])->save();

        return $offer->fresh() ?? $offer;
    }

    public function delete(Offer $offer): void
    {
        if (Schema::hasTable('applications') && DB::table('applications')->where('offer_id', $offer->id)->exists()) {
            throw new UnprocessableEntityHttpException('Offer has applications and cannot be deleted: close or archive it instead.');
        }

        $offer->delete();
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Offer::where('public_token', $token)->exists());

        return $token;
    }

    private function normalized(mixed $value): string
    {
        return json_encode($value ?? []) ?: '[]';
    }
}
