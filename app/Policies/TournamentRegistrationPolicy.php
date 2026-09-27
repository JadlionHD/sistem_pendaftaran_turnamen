<?php

namespace App\Policies;

use App\Models\TournamentRegistration;
use App\Models\User;

class TournamentRegistrationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TournamentRegistration $registration): bool
    {
        return (bool) $user->is_admin || (int) $user->id === (int) $registration->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model (e.g. edit team data).
     */
    public function update(User $user, TournamentRegistration $registration): bool
    {
        if ($user->is_admin) {
            return true;
        }

        // Peserta hanya boleh ubah jika miliknya sendiri dan statusnya masih pending
        return (int) $user->id === (int) $registration->user_id && $registration->status === 'pending';
    }

    /**
     * Determine whether the user can update the approval status.
     */
    public function updateStatus(User $user, TournamentRegistration $registration): bool
    {
        return (bool) $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TournamentRegistration $registration): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return (int) $user->id === (int) $registration->user_id && $registration->status === 'pending';
    }
}
