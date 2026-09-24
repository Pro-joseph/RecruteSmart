<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferFormFieldResource;
use App\Services\Applications\ApplicationSubmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOfferController extends Controller
{
    public function __construct(private readonly ApplicationSubmitter $submitter) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $offer = $this->submitter->findOfferOrFail($token);
        $reason = $this->submitter->acceptingReason($offer);

        return response()->json([
            'data' => [
                'title' => $offer->title,
                'type' => $offer->type->value,
                'type_label' => $offer->type_label,
                'city' => $offer->city,
                'country' => $offer->country,
                'work_mode' => $offer->work_mode?->value,
                'description' => $offer->description,
                'missions' => $offer->missions,
                'profile_wanted' => $offer->profile_wanted,
                'salary_min' => $offer->salary_min,
                'salary_max' => $offer->salary_max,
                'salary_currency' => $offer->salary_currency,
                'deadline_at' => $offer->deadline_at?->toIso8601String(),
                'accepting_applications' => $reason === null,
                'closure_reason' => $reason,
                'form_fields' => OfferFormFieldResource::collection(
                    $offer->formFields->where('is_hidden', false)->values()
                ),
            ],
        ]);
    }
}
