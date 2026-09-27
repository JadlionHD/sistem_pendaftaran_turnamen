<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Models\Game;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    /**
     * Display a listing of tournaments with filtering.
     */
    public function index(Request $request): Response
    {
        $gameId = $request->query('game_id');
        $status = $request->query('status');

        $query = Tournament::query()
            ->with(['game', 'organizer'])
            ->withCount(['registrations as approved_teams_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->latest('id');

        if ($gameId) {
            $query->where('game_id', $gameId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $tournaments = $query->paginate(9)->withQueryString();
        $games = Game::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('tournaments/Index', [
            'tournaments' => $tournaments,
            'games' => $games,
            'filters' => [
                'game_id' => $gameId,
                'status' => $status ?? 'all',
            ],
            'canManage' => $request->user()?->is_admin ?? false,
        ]);
    }

    /**
     * Show the form for creating a new tournament.
     */
    public function create(): Response
    {
        Gate::authorize('create', Tournament::class);

        $games = Game::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('tournaments/Create', [
            'games' => $games,
        ]);
    }

    /**
     * Store a newly created tournament in storage.
     */
    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Tournament::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        Tournament::create([
            ...$validated,
            'slug' => $slug,
            'organizer_id' => (int) $request->user()->id,
        ]);

        return redirect()->route('tournaments.index')
            ->with('success', 'Turnamen berhasil dibuat dan dipublikasikan!');
    }

    /**
     * Display the specified tournament.
     */
    public function show(Tournament $tournament): Response
    {
        $tournament->load([
            'game',
            'organizer',
            'registrations' => function ($q) {
                $q->where('status', 'approved')->latest();
            },
        ]);

        $tournament->loadCount([
            'registrations as approved_teams_count' => function ($q) {
                $q->where('status', 'approved');
            },
            'registrations as total_registrations_count',
        ]);

        $user = auth()->user();
        $userRegistration = $user
            ? $tournament->registrations()->where('user_id', $user->id)->latest()->first()
            : null;

        return Inertia::render('tournaments/Show', [
            'tournament' => $tournament,
            'userRegistration' => $userRegistration,
            'canManage' => $user?->is_admin ?? false,
        ]);
    }

    /**
     * Show the form for editing the specified tournament.
     */
    public function edit(Tournament $tournament): Response
    {
        Gate::authorize('update', $tournament);

        $games = Game::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('tournaments/Edit', [
            'tournament' => $tournament->load('game'),
            'games' => $games,
        ]);
    }

    /**
     * Update the specified tournament in storage.
     */
    public function update(UpdateTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $validated = $request->validated();

        if ($tournament->title !== $validated['title']) {
            $baseSlug = Str::slug($validated['title']);
            $slug = $baseSlug;
            $counter = 1;
            while (Tournament::where('slug', $slug)->where('id', '!=', $tournament->id)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }
            $validated['slug'] = $slug;
        }

        $tournament->update($validated);

        return redirect()->route('tournaments.show', $tournament->id)
            ->with('success', 'Data turnamen berhasil diperbarui!');
    }

    /**
     * Remove the specified tournament from storage.
     */
    public function destroy(Tournament $tournament): RedirectResponse
    {
        Gate::authorize('delete', $tournament);

        $tournament->delete();

        return redirect()->route('tournaments.index')
            ->with('success', 'Turnamen berhasil dihapus.');
    }
}
