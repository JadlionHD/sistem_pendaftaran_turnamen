<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RegistrationApiController;
use App\Http\Controllers\Api\V1\TournamentApiController;
use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/status', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Sistem Pendaftaran Turnamen Game API running',
        'framework' => 'Laravel 13',
        'database' => config('database.default'),
    ]);
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')->name('login');

    // Endpoint Publik
    Route::get('games', function () {
        return response()->json([
            'status' => 'success',
            'data' => Game::where('is_active', true)->orderBy('name')->get(),
        ]);
    })->name('games.index');

    Route::get('tournaments', [TournamentApiController::class, 'index'])->name('tournaments.index');
    Route::get('tournaments/{tournament}', [TournamentApiController::class, 'show'])->name('tournaments.show');

    // Endpoint Terproteksi Token Sanctum
    Route::middleware(['auth:sanctum', 'throttle:api-v1'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        // Ringkasan Dashboard / Laporan (Admin only)
        Route::get('reports/summary', function () {
            Gate::authorize('manage-tournaments');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'tournament_count' => Tournament::count(),
                    'registration_count' => TournamentRegistration::count(),
                    'game_count' => Game::count(),
                ],
            ]);
        })->name('reports.summary');

        // Modul Turnamen (Admin)
        Route::post('tournaments', [TournamentApiController::class, 'store'])->name('tournaments.store');
        Route::put('tournaments/{tournament}', [TournamentApiController::class, 'update'])->name('tournaments.update');
        Route::delete('tournaments/{tournament}', [TournamentApiController::class, 'destroy'])->name('tournaments.destroy');

        // Alur Transaksi Pendaftaran Tim
        Route::get('registrations', [RegistrationApiController::class, 'index'])->name('registrations.index');
        Route::post('registrations', [RegistrationApiController::class, 'store'])->name('registrations.store');
        Route::get('registrations/{registration}', [RegistrationApiController::class, 'show'])->name('registrations.show');
        Route::put('registrations/{registration}', [RegistrationApiController::class, 'update'])->name('registrations.update');
        Route::patch('registrations/{registration}/status', [RegistrationApiController::class, 'updateStatus'])->name('registrations.update-status');
        Route::delete('registrations/{registration}', [RegistrationApiController::class, 'destroy'])->name('registrations.destroy');

        // Alternatif endpoint registrasi nested
        Route::post('tournaments/{tournament}/register', function (Request $request, Tournament $tournament) {
            $user = $request->user();

            if (! $tournament->canAcceptRegistrations()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Turnamen tidak dapat menerima pendaftaran.',
                ], 422);
            }

            $validated = $request->validate([
                'team_name' => ['required', 'string', 'min:3', 'max:50'],
                'captain_name' => ['required', 'string', 'min:3', 'max:100'],
                'captain_whatsapp' => ['required', 'string', 'min:9', 'max:20'],
                'captain_email' => ['required', 'email'],
                'team_members' => ['required', 'string', 'min:5'],
            ]);

            $registration = TournamentRegistration::create([
                ...$validated,
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pendaftaran tim berhasil dikirim!',
                'data' => $registration,
            ], 201);
        })->name('tournaments.register');
    });
});
