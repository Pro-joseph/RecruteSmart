<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\AddNoteRequest;
use App\Http\Requests\Applications\BulkStatusRequest;
use App\Http\Requests\Applications\ListApplicationsRequest;
use App\Http\Requests\Applications\UpdateStatusRequest;
use App\Http\Resources\ApplicationDetailResource;
use App\Http\Resources\ApplicationEventResource;
use App\Http\Resources\ApplicationResource;
use App\Jobs\AnalyzeApplicationJob;
use App\Models\Application;
use App\Models\Offer;
use App\Models\Skill;
use App\Services\Applications\ApplicationFilter;
use App\Services\Applications\ApplicationUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationController extends Controller
{
    public function index(ListApplicationsRequest $request, Offer $offer): JsonResponse
    {
        $applications = app(ApplicationFilter::class)
            ->apply($offer->applications()->getQuery(), $request->validated())
            ->with(['analysis', 'skills', 'offer:id,criteria_version'])
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApplicationResource::collection($applications)->response($request);
    }

    /**
     * Distinct skills held by this offer's candidates, with their application count (EF-803).
     */
    public function skills(Offer $offer): JsonResponse
    {
        Gate::authorize('view', $offer);

        $skills = Skill::query()
            ->join('application_skill', 'skills.id', '=', 'application_skill.skill_id')
            ->whereIn('application_skill.application_id', $offer->applications()->pluck('id'))
            ->groupBy('skills.id', 'skills.name', 'skills.slug')
            ->select('skills.id', 'skills.name', 'skills.slug')
            ->selectRaw('count(*) as applications_count')
            ->orderBy('skills.name')
            ->get();

        return response()->json(['data' => $skills]);
    }

    public function reanalyze(Application $application): JsonResponse
    {
        $application->load('offer');
        Gate::authorize('update', $application);

        AnalyzeApplicationJob::dispatch($application->id);

        return response()->json(['data' => ['status' => 'queued']], 202);
    }

    public function show(Application $application): JsonResponse
    {
        $application->load(['offer.formFields', 'analysis', 'skills', 'interviews']);
        Gate::authorize('view', $application);

        return (new ApplicationDetailResource($application))->response();
    }

    public function events(Request $request, Application $application): JsonResponse
    {
        $application->load('offer');
        Gate::authorize('view', $application);

        $events = $application->events()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return ApplicationEventResource::collection($events)->response($request);
    }

    public function updateStatus(UpdateStatusRequest $request, Application $application): JsonResponse
    {
        $status = ApplicationStatus::from((string) $request->validated('status'));

        app(ApplicationUpdater::class)->changeStatus($application, $status, $request->user());

        return response()->json(['data' => ['status' => $application->status->value]]);
    }

    public function addNote(AddNoteRequest $request, Application $application): JsonResponse
    {
        $data = $request->validated();

        app(ApplicationUpdater::class)->addNote(
            $application,
            isset($data['note']) ? (string) $data['note'] : null,
            isset($data['rating']) ? (int) $data['rating'] : null,
            $request->user(),
        );

        return response()->json(['data' => ['rating' => $application->rating]]);
    }

    public function bulkStatus(BulkStatusRequest $request, Offer $offer): JsonResponse
    {
        $ids = array_map('intval', (array) $request->validated('application_ids'));
        $status = ApplicationStatus::from((string) $request->validated('status'));
        $updater = app(ApplicationUpdater::class);

        $updated = 0;
        foreach ($offer->applications()->whereKey($ids)->get() as $application) {
            if ($updater->changeStatus($application, $status, $request->user())) {
                $updated++;
            }
        }

        return response()->json(['data' => ['updated' => $updated]]);
    }

    public function download(Request $request, Application $application, string $key): StreamedResponse
    {
        $application->load('offer');
        Gate::authorize('view', $application);

        if ($key === 'cv') {
            $path = $application->cv_path;
            $name = $application->cv_original_name;
        } else {
            $match = collect($application->files ?? [])->firstWhere('key', $key);
            if (! is_array($match) || ! isset($match['path'], $match['original_name'])) {
                throw new NotFoundHttpException('Fichier introuvable.');
            }
            $path = (string) $match['path'];
            $name = (string) $match['original_name'];
        }

        if (! Storage::disk('private')->exists($path)) {
            throw new NotFoundHttpException('Fichier introuvable.');
        }

        return Storage::disk('private')->download($path, $name);
    }
}
