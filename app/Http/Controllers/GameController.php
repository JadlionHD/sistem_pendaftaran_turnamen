<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Display a listing of games.
     */
    public function index(Request $request): Response
    {
        $games = Game::withCount('tournaments')->orderBy('name')->get();

        return Inertia::render('games/Index', [
            'games' => $games,
            'canManage' => $request->user()?->is_admin ?? false,
        ]);
    }

    /**
     * Store a newly created game in storage (Admin only).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-games');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:games,name'],
            'genre' => ['required', 'string', 'max:50'],
            'team_size' => ['required', 'integer', 'min:1', 'max:20'],
            'platform' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Game::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
            'is_active' => true,
        ]);

        return redirect()->route('games.index')
            ->with('success', "Game {$validated['name']} berhasil ditambahkan!");
    }

    /**
     * Update the specified game in storage (Admin only).
     */
    public function update(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('manage-games');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:games,name,'.$game->id],
            'genre' => ['required', 'string', 'max:50'],
            'team_size' => ['required', 'integer', 'min:1', 'max:20'],
            'platform' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $game->update([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return redirect()->route('games.index')
            ->with('success', "Game {$game->name} berhasil diperbarui!");
    }

    /**
     * Remove the specified game from storage (Admin only).
     */
    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('manage-games');

        if ($game->tournaments()->exists()) {
            return back()->with('error', 'Game tidak dapat dihapus karena masih memiliki turnamen yang terhubung.');
        }

        $gameName = $game->name;
        $game->delete();

        return redirect()->route('games.index')
            ->with('success', "Game {$gameName} berhasil dihapus.");
    }
}
