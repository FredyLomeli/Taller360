<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Calculamos la pantalla de inicio según el rol.
        // redirect()->away() fuerza una recarga completa de página, evitando que
        // Inertia haga peticiones XHR intermedias que chocan con el middleware de roles.
        $homeRoute = match (Auth::user()->role) {
            'produccion' => route('production.plan'),
            default      => route('dashboard'),
        };

        // Respetamos si el usuario intentó entrar a una URL específica antes del login
        $intendedUrl = session()->pull('url.intended', $homeRoute);

        return redirect()->away($intendedUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Usamos away() para forzar recarga completa de página,
        // evitando que Inertia renderice la página pública dentro del layout autenticado.
        return redirect()->away('/');
    }
}