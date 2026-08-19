<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda as telas que só o papel 'admin' pode ver: convidar gente e gerir
 * a empresa. 'operador' lança estoque, mas não decide quem mais entra.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Só um admin da empresa pode acessar esta página.');
        }

        return $next($request);
    }
}
