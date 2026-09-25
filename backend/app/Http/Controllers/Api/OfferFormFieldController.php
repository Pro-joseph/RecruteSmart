<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\SyncFormFieldsRequest;
use App\Http\Resources\OfferFormFieldResource;
use App\Models\Offer;
use App\Services\Offers\FormFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfferFormFieldController extends Controller
{
    public function __construct(private readonly FormFieldService $fields) {}

    public function catalog(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Offer::class);

        $catalog = collect($this->fields->catalog())->map(
            fn (array $def, string $key): array => ['key' => $key, ...$def]
        )->values();

        return response()->json(['data' => $catalog]);
    }

    public function index(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('manageFormFields', $offer);
        $offer->load('formFields');

        return OfferFormFieldResource::collection($offer->formFields)->response($request);
    }

    public function update(SyncFormFieldsRequest $request, Offer $offer): JsonResponse
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $request->validated()['fields'];
        $this->fields->sync($offer, $fields);
        $offer->load('formFields');

        return OfferFormFieldResource::collection($offer->formFields)->response($request);
    }
}
