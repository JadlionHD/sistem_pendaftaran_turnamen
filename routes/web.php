<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TournamentAiChatController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentRegistrationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::post('/login', [SessionController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::post('/logout', [SessionController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');

// Halaman Publik: Daftar Turnamen & Detail
Route::get('/', [TournamentController::class, 'index'])->name('home');
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');

// Rute yang membutuhkan autentikasi
Route::middleware(['auth'])->group(function () {
    // 1. Modul Turnamen (Admin) - Harus di atas wildcard {tournament}
    Route::get('/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');

    // 2. Alur Pendaftaran Turnamen
    Route::get('/tournaments/{tournament}/register', [TournamentRegistrationController::class, 'create'])->name('registrations.create');
    Route::get('/registrations', [TournamentRegistrationController::class, 'index'])->name('registrations.index');
    Route::post('/registrations', [TournamentRegistrationController::class, 'store'])->name('registrations.store');
    Route::get('/registrations/{registration}/edit', [TournamentRegistrationController::class, 'edit'])->name('registrations.edit');
    Route::put('/registrations/{registration}', [TournamentRegistrationController::class, 'update'])->name('registrations.update');
    Route::patch('/registrations/{registration}/status', [TournamentRegistrationController::class, 'updateStatus'])->name('registrations.update-status');
    Route::post('/registrations/{registration}/audit-ai', [TournamentRegistrationController::class, 'auditAi'])->name('registrations.audit-ai');
    Route::delete('/registrations/{registration}', [TournamentRegistrationController::class, 'destroy'])->name('registrations.destroy');

    // 3. Master Data Games
    Route::resource('games', GameController::class)->only(['index', 'store', 'update', 'destroy']);

    // 4. Undangan Tim Bawaan Starter Kit
    Route::post('invitations/{invitation}/accept', [
        TeamInvitationController::class,
        'accept',
    ])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [
        TeamInvitationController::class,
        'decline',
    ])->name('invitations.decline');
});

// Detail Turnamen publik (ditaruh di bawah agar tidak menabrak /tournaments/create)
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

// AI Assistant & Pengecek Turnamen Publik (Live Streaming - Dapat digunakan oleh Guest)
Route::post('/tournaments/{tournament}/ai-chat-stream', [TournamentAiChatController::class, 'stream'])
    ->middleware('throttle:30,1')
    ->name('tournaments.ai-chat-stream');

Route::post('/tournaments/{tournament}/ai-chat-reset', [TournamentAiChatController::class, 'reset'])
    ->middleware('throttle:30,1')
    ->name('tournaments.ai-chat-reset');

Route::post('/tournaments/{tournament}/ai-chat-compact', [TournamentAiChatController::class, 'compact'])
    ->middleware('throttle:30,1')
    ->name('tournaments.ai-chat-compact');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

require __DIR__.'/settings.php';
