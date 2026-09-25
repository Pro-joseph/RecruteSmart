<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interviews\PlanInterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Application;
use App\Services\Interviews\InterviewPlanner;
use Illuminate\Http\JsonResponse;

class InterviewController extends Controller
{
    /** Plan an interview (EF-902) — moves the candidate to « interview » and sends the .ics invite. */
    public function store(PlanInterviewRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $interview = app(InterviewPlanner::class)->plan(
            $application,
            $request->validated(),
            $user,
        );

        return (new InterviewResource($interview))->response()->setStatusCode(201);
    }
}
