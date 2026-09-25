<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Jobs\AnalyzeApplicationJob;
use App\Models\Application;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationController extends Controller
{
    /**
     * @var list<string>
     */
    private const SORTABLE = ['created_at', 'full_name'];

    public function index(Request $request, Offer $offer): JsonResponse
    {
        Gate::authorize('view', $offer);

        $sort = (string) $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, self::SORTABLE, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        $perPage = min($request->integer('per_page', 25), 100);

        $applications = $offer->applications()
            ->with(['analysis', 'skills', 'offer:id,criteria_version'])
            ->orderBy($column, $direction)
            ->paginate($perPage);

        return ApplicationResource::collection($applications)->response($request);
    }

    public function reanalyze(Application $application): JsonResponse
    {
        $application->load('offer');
        Gate::authorize('update', $application);

        AnalyzeApplicationJob::dispatch($application->id);

        return response()->json(['data' => ['status' => 'queued']], 202);
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
