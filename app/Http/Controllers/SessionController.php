<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Authenticate user session for SPA / Inertia and API.
     */
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $isInertia = $request->header('X-Inertia') || ! $request->expectsJson();

        if (! Auth::guard('web')->attempt($data, (bool) $request->boolean('remember'))) {
            if ($isInertia) {
                throw ValidationException::withMessages([
                    'email' => 'Kredensial tidak valid atau password salah.',
                ]);
            }

            return response()->json(['message' => 'Kredensial tidak valid.'], 401);
        }

        $request->session()->regenerate();
        $user = Auth::guard('web')->user();

        if ($isInertia) {
            return redirect()->intended(route('tournaments.index'));
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Terminate the authenticated user session.
     */
    public function logout(Request $request): Response|RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->header('X-Inertia') || ! $request->expectsJson()) {
            return redirect()->route('home');
        }

        return response()->noContent();
    }
}
