<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Si no está logueado o su rol no coincide con el requerido
        if (! $request->user() || $request->user()->role !== $role) {
            // Lo mandamos al dashboard o mostramos error 403
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
