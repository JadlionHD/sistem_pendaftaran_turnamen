<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\TeamInvitation;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $tournamentsCount = Tournament::where('status', 'open')->count();
        $myRegistrationsCount = TournamentRegistration::where('user_id', $request->user()->id)->count();
        $gamesCount = Game::count();
        $recentTournaments = Tournament::with('game')->latest('id')->take(3)->get();

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'stats' => [
                'openTournaments' => $tournamentsCount,
                'myRegistrations' => $myRegistrationsCount,
                'gamesCount' => $gamesCount,
            ],
            'recentTournaments' => $recentTournaments,
        ]);
    }
}
