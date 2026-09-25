<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ForwardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forwards\CreateForwardRequest;
use App\Http\Resources\ForwardResource;
use App\Jobs\SendForwardJob;
use App\Models\Forward;
use App\Services\Forwards\ForwardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForwardController extends Controller
{
    /** Create a forward and queue its delivery (spec §7, EF-1001→1006). */
    public function store(CreateForwardRequest $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $forward = app(ForwardService::class)->create($user, $request->validated());

        return (new ForwardResource($forward->load('snapshots')))->response()->setStatusCode(202);
    }

    /** Transfer history: who, when, to whom, which candidates, outcome (EF-1007). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $forwards = $user->forwards()
            ->with('snapshots')
            ->latest('created_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ForwardResource::collection($forwards)->response($request);
    }

    public function show(Request $request, Forward $forward): JsonResponse
    {
        if ($forward->user_id !== $request->user()?->id) {
            abort(403);
        }

        return (new ForwardResource($forward->load('snapshots')))->response();
    }

    /** Re-queue a failed or undelivered transfer (EF-1008). */
    public function retry(Request $request, Forward $forward): JsonResponse
    {
        if ($forward->user_id !== $request->user()?->id) {
            abort(403);
        }

        abort_unless($forward->status !== ForwardStatus::Sent, 422, 'Ce transfert a déjà été envoyé.');

        $forward->update(['status' => ForwardStatus::Queued, 'error_message' => null]);
        SendForwardJob::dispatch($forward->id);

        return response()->json(['message' => 'Transfert remis en file d\'envoi.']);
    }
}
