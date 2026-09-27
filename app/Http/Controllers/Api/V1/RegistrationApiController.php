<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\TournamentRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RegistrationApiController extends Controller
{
    /**
     * Display a listing of registrations.
     * Admin sees all registrations; Participants see only their own.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'per_page.max' => 'Parameter per_page maksimal adalah 50.',
            'per_page.min' => 'Parameter per_page minimal adalah 1.',
        ]);

        $user = $request->user();
        $query = TournamentRegistration::with(['tournament.game', 'user'])->latest('id');

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->query('tournament_id'));
        }

        $perPage = (int) $request->query('per_page', 10);
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function (TournamentRegistration $reg) {
            $data = $reg->toArray();
            $data['owner'] = [
                'id' => $reg->user_id,
                'name' => $reg->user?->name,
            ];
            if (isset($data['user'])) {
                unset($data['user']['password'], $data['user']['remember_token'], $data['user']['email_verified_at']);
            }

            return $data;
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
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
     * Store a newly created registration in storage.
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        // Cegah manipulasi identitas pemilik (user_id spoofing)
        if ($request->has('user_id') && (int) $request->input('user_id') !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Tidak diizinkan memalsukan identitas pendaftar (user_id).',
                'errors' => ['user_id' => ['user_id tidak boleh dimanipulasi.']],
            ], 422);
        }

        $registration = TournamentRegistration::create([
            ...$request->validated(),
            'user_id' => (int) $request->user()->id,
            'status' => 'pending',
        ]);

        $registration->load(['tournament.game', 'user']);
        $data = $registration->toArray();
        $data['owner'] = [
            'id' => $registration->user_id,
            'name' => $registration->user?->name,
        ];
        if (isset($data['user'])) {
            unset($data['user']['password'], $data['user']['remember_token'], $data['user']['email_verified_at']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pendaftaran tim berhasil dikirim! Menunggu verifikasi dari panitia.',
            'data' => $data,
        ], 201)->header('Location', url("/api/v1/registrations/{$registration->id}"));
    }

    /**
     * Display the specified registration.
     */
    public function show(TournamentRegistration $registration): JsonResponse
    {
        Gate::authorize('view', $registration);

        $registration->load(['tournament.game', 'user']);
        $data = $registration->toArray();
        $data['owner'] = [
            'id' => $registration->user_id,
            'name' => $registration->user?->name,
        ];
        if (isset($data['user'])) {
            unset($data['user']['password'], $data['user']['remember_token'], $data['user']['email_verified_at']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Update the specified registration in storage.
     */
    public function update(UpdateRegistrationRequest $request, TournamentRegistration $registration): JsonResponse
    {
        Gate::authorize('update', $registration);

        $registration->update($request->validated());

        $registration->load(['tournament.game', 'user']);
        $data = $registration->toArray();
        $data['owner'] = [
            'id' => $registration->user_id,
            'name' => $registration->user?->name,
        ];
        if (isset($data['user'])) {
            unset($data['user']['password'], $data['user']['remember_token'], $data['user']['email_verified_at']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pendaftaran tim berhasil diperbarui!',
            'data' => $data,
        ]);
    }

    /**
     * Update the approval status of a registration (Admin only).
     */
    public function updateStatus(Request $request, TournamentRegistration $registration): JsonResponse
    {
        Gate::authorize('updateStatus', $registration);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,pending'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === 'approved') {
            $tournament = $registration->tournament;
            if ($tournament->isFull() && $registration->status !== 'approved') {
                return response()->json([
                    'message' => 'Tidak dapat menyetujui tim: Kuota turnamen sudah mencapai batas maksimal!',
                    'errors' => ['status' => ['Kuota turnamen sudah penuh.']],
                ], 422);
            }
        }

        $registration->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Status pendaftaran tim berhasil diperbarui menjadi {$validated['status']}.",
            'data' => $registration->fresh(['tournament', 'user']),
        ]);
    }

    /**
     * Remove the specified registration from storage.
     */
    public function destroy(TournamentRegistration $registration): Response
    {
        Gate::authorize('delete', $registration);

        $registration->delete();

        return response()->noContent();
    }
}
