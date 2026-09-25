<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** EF-1205: only the admin account reaches /admin/* (quota, cost and user supervision). */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_admin) {
            abort(403, 'Accès réservé à l\'administrateur.');
        }

        return $next($request);
    }
}
