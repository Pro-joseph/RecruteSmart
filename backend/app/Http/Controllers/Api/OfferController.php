<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreOfferRequest;
use App\Http\Requests\Offers\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Jobs\AnalyzeApplicationJob;
use App\Models\Offer;
use App\Services\Audit\AuditLogger;
use App\Services\Offers\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Offer::class);

        $query = Offer::query()
            ->forUser($request->user()->id)
            ->with('formFields')
            ->withCount([
                'applications',
                'applications as new_applications_count' => fn ($q) => $q->where('status', 'new'),
            ])
            ->latest();

        if ($request->filled('status')) {
            $request->validate(['status' => ['string', 'in:draft,published,closed,archived']]);
            $query->where('status', $request->string('status')->toString());
        }

        $perPage = min($request->integer('per_page', 25), 100);

        return OfferResource::collection($query->paginate($perPage))->response($request);
    }

    public function store(StoreOfferRequest $request): JsonResponse
    {
        $offer = $this->offers->create($request->user(), $request->validated());
        $offer->load('formFields');

        return (new OfferResource($offer))->response($request)->setStatusCode(201);
    }

    public function show(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('view', $offer);
        $offer->load('formFields')->loadCount([
            'applications',
            'applications as new_applications_count' => fn ($q) => $q->where('status', 'new'),
        ]);

        return (new OfferResource($offer))->response($request);
    }

    public function update(UpdateOfferRequest $request, Offer $offer): JsonResponse
    {
        $updated = $this->offers->update($offer, $request->validated());
        $updated->load('formFields');

        return (new OfferResource($updated))->response($request);
    }

    public function destroy(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('delete', $offer);
        $this->offers->delete($offer);

        return response()->json(null, 204);
    }

    public function publish(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('publish', $offer);
        $published = $this->offers->publish($offer);
        $published->load('formFields');

        return (new OfferResource($published))->response($request);
    }

    public function close(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('close', $offer);
        $closed = $this->offers->close($offer);
        $closed->load('formFields');

        return (new OfferResource($closed))->response($request);
    }

    public function duplicate(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('duplicate', $offer);
        $copy = $this->offers->duplicate($request->user(), $offer);
        $copy->load('formFields');

        return (new OfferResource($copy))->response($request)->setStatusCode(201);
    }

    public function regenerateLink(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('regenerateLink', $offer);
        $updated = $this->offers->regenerateLink($offer);

        app(AuditLogger::class)->log(
            $request->user(),
            AuditAction::LinkRegenerated,
            $offer,
            null,
            $request->ip(),
        );
        $updated->load('formFields');

        return (new OfferResource($updated))->response($request);
    }

    public function reanalyze(Offer $offer): JsonResponse
    {
        Gate::authorize('update', $offer);

        $staleIds = $offer->applications()
            ->whereHas('analysis', fn ($q) => $q->where(
                'application_analyses.criteria_version',
                '<',
                $offer->criteria_version,
            ))
            ->pluck('id');

        foreach ($staleIds as $id) {
            AnalyzeApplicationJob::dispatch((int) $id);
        }

        return response()->json(['data' => ['queued' => $staleIds->count()]], 202);
    }
}
