<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TournamentApiController extends Controller
{
    /**
     * Display a listing of tournaments with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'per_page.max' => 'Parameter per_page maksimal adalah 50.',
            'per_page.min' => 'Parameter per_page minimal adalah 1.',
        ]);

        $query = Tournament::with(['game', 'organizer'])
            ->withCount(['registrations as approved_teams_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->latest('id');

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->query('game_id'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $paginated->items(),
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Display the specified tournament.
     */
    public function show(Tournament $tournament): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $tournament->load(['game', 'organizer', 'registrations']),
        ]);
    }

    /**
     * Store a newly created tournament in storage (Admin only).
     */
    public function store(StoreTournamentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (Tournament::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $tournament = Tournament::create([
            ...$validated,
            'slug' => $slug,
            'organizer_id' => (int) $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Turnamen berhasil dibuat dan dipublikasikan!',
            'data' => $tournament->load(['game', 'organizer']),
        ], 201)->header('Location', url("/api/v1/tournaments/{$tournament->id}"));
    }

    /**
     * Update the specified tournament in storage (Admin only).
     */
    public function update(UpdateTournamentRequest $request, Tournament $tournament): JsonResponse
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

        return response()->json([
            'status' => 'success',
            'message' => 'Data turnamen berhasil diperbarui!',
            'data' => $tournament->fresh(['game', 'organizer']),
        ]);
    }

    /**
     * Remove the specified tournament from storage (Admin only).
     */
    public function destroy(Tournament $tournament): Response
    {
        Gate::authorize('delete', $tournament);

        $tournament->delete();

        return response()->noContent();
    }
}
