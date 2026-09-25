<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EF-1205: admin supervision — accounts (list) and AI usage
 * (analyses run, token consumption and estimated cost per model).
 */
class AdminUserController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $users = DB::table('users')
            ->leftJoin('offers', 'offers.user_id', '=', 'users.id')
            ->leftJoin('applications', 'applications.offer_id', '=', 'offers.id')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.is_admin', 'users.created_at')
            ->selectRaw(
                'users.id, users.name, users.email, users.is_admin, users.created_at,
                 count(distinct offers.id) as offers_count,
                 count(applications.id) as applications_count'
            )
            ->orderBy('users.created_at')
            ->get()
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at,
                'offers_count' => (int) $user->offers_count,
                'applications_count' => (int) $user->applications_count,
            ]);

        return response()->json(['data' => $users]);
    }

    public function usage(): JsonResponse
    {
        $totals = DB::table('application_analyses')
            ->selectRaw(
                'count(*) as analyses_count,
                 sum(case when status = \'completed\' then 1 else 0 end) as completed_count,
                 sum(case when status = \'failed\' then 1 else 0 end) as failed_count,
                 coalesce(sum(tokens_in), 0) as tokens_in,
                 coalesce(sum(tokens_out), 0) as tokens_out'
            )
            ->first();

        $perModel = DB::table('application_analyses')
            ->groupBy('llm_provider', 'llm_model')
            ->selectRaw(
                'llm_provider, llm_model,
                 count(*) as analyses_count,
                 coalesce(sum(tokens_in), 0) as tokens_in,
                 coalesce(sum(tokens_out), 0) as tokens_out'
            )
            ->orderByDesc('tokens_in')
            ->get();

        return response()->json(['data' => [
            'totals' => $totals,
            'per_model' => $perModel,
        ]]);
    }
}
