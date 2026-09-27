<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TournamentRegistrationController extends Controller
{
    /**
     * Display a listing of registrations.
     * Admin sees all registrations; Participants see only their own.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = (bool) $user->is_admin;
        $status = $request->query('status');
        $tournamentId = $request->query('tournament_id');

        $query = TournamentRegistration::query()
            ->with(['tournament.game', 'user'])
            ->latest('id');

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($tournamentId) {
            $query->where('tournament_id', $tournamentId);
        }

        $registrations = $query->paginate(10)->withQueryString();
        $tournaments = Tournament::orderBy('title')->get(['id', 'title']);

        return Inertia::render('registrations/Index', [
            'registrations' => $registrations,
            'tournaments' => $tournaments,
            'filters' => [
                'status' => $status ?? 'all',
                'tournament_id' => $tournamentId,
            ],
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Show the form for creating a new registration for a specific tournament.
     */
    public function create(Tournament $tournament): Response|RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Cek apakah turnamen bisa menerima pendaftaran
        if (! $tournament->canAcceptRegistrations()) {
            return redirect()->route('tournaments.show', $tournament->id)
                ->with('error', 'Turnamen ini tidak dapat menerima pendaftaran baru (kuota penuh atau sudah ditutup).');
        }

        // Cek apakah tim kapten sudah pernah mendaftar
        $existing = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('registrations.index')
                ->with('error', 'Anda sudah mendaftarkan tim "'.$existing->team_name.'" pada turnamen ini.');
        }

        return Inertia::render('registrations/Create', [
            'tournament' => $tournament->load('game'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Store a newly created registration in storage.
     */
    public function store(StoreRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        TournamentRegistration::create([
            ...$validated,
            'user_id' => (int) $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()->route('registrations.index')
            ->with('success', 'Pendaftaran tim berhasil dikirim! Menunggu verifikasi dari panitia.');
    }

    /**
     * Show the form for editing the specified registration.
     */
    public function edit(TournamentRegistration $registration): Response
    {
        Gate::authorize('update', $registration);

        return Inertia::render('registrations/Edit', [
            'registration' => $registration->load('tournament.game'),
        ]);
    }

    /**
     * Update the specified registration in storage.
     */
    public function update(UpdateRegistrationRequest $request, TournamentRegistration $registration): RedirectResponse
    {
        $validated = $request->validated();

        $registration->update($validated);

        return redirect()->route('registrations.index')
            ->with('success', 'Data pendaftaran tim berhasil diperbarui!');
    }

    /**
     * Update the approval status of a registration (Admin only).
     */
    public function updateStatus(Request $request, TournamentRegistration $registration): RedirectResponse
    {
        Gate::authorize('updateStatus', $registration);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,pending'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === 'approved') {
            $tournament = $registration->tournament;
            if ($tournament->isFull() && $registration->status !== 'approved') {
                return back()->with('error', 'Tidak dapat menyetujui tim: Kuota turnamen sudah mencapai batas maksimal!');
            }
        }

        $registration->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        $statusLabel = match ($validated['status']) {
            'approved' => 'disetujui',
            'rejected' => 'ditolak',
            default => 'diubah menjadi pending',
        };

        return back()->with('success', "Pendaftaran tim {$registration->team_name} berhasil {$statusLabel}.");
    }

    /**
     * Remove the specified registration from storage.
     */
    public function destroy(TournamentRegistration $registration): RedirectResponse
    {
        Gate::authorize('delete', $registration);

        $teamName = $registration->team_name;
        $registration->delete();

        return redirect()->route('registrations.index')
            ->with('success', "Pendaftaran tim {$teamName} berhasil dibatalkan/dihapus.");
    }
}
